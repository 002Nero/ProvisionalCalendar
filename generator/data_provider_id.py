from typing import Dict, Any, Tuple
import logging
import pandas as pd
from sqlalchemy import create_engine

from function import (
    get_availability_prof_from_unavailable,
    get_availability_room_from_unavailable,
    get_availability_group_from_unavailable,
    get_availability_slot_from_unavailable,
    convert_days_int_to_string,
    convert_day_string_to_int,
    _time_to_slot,
    get_start_time,
    get_end_time,
)


# ==============================================================================
# CLASSE 1: GESTION DES DONNÉES (DataProvider)
# ==============================================================================
class DataProviderID:
    """
    Responsable de la connexion à la BDD et de la préparation de toutes les
    données nécessaires pour le modèle d'optimisation.
    """

    def __init__(self, db_config: Dict[str, Any]):
        self.db_config = db_config
        self.engine = create_engine(
            f"mysql+mysqlconnector://{db_config['user']}:{db_config['password']}@"
            f"{db_config['host']}:{db_config['port']}/{db_config['database']}"
        )

        #TODO changer pour que ce ne soit plus en dur.
        self.group_map = {
            "BUT1": ["G1", "G2", "G3", "G1A", "G2A", "G3A", "G1B", "G2B", "G3B"],
            "BUT2": ["G4", "G5", "G4A", "G5A", "G4B", "G5B"],
            "BUT3": ["G7", "G8", "G7A", "G7B", "G8A"]
        }

    def _get_generic_constraints(self, table_name: str, id_col: str, week_id: int) -> pd.DataFrame:
        query = f"""
            SELECT {id_col}, day_of_week, start_time, end_time, priority, week_id
            FROM {table_name}
            WHERE active = 1
              AND (week_id = %(week_id)s OR week_id IS NULL)
              AND (
                week_id = %(week_id)s
                OR (week_id IS NULL
                    AND NOT EXISTS (
                        SELECT 1 FROM {table_name} t2
                        WHERE t2.{id_col} = {table_name}.{id_col}
                          AND t2.day_of_week = {table_name}.day_of_week
                          AND t2.week_id = %(week_id)s
                          AND t2.active = 1
                    )
                )
              )
        """
        try:
            df = pd.read_sql(query, self.engine, params={"week_id": week_id})
            if df.empty:
                logging.warning(f"Aucune contrainte trouvée dans {table_name} pour la semaine {week_id}.")
            return df
        except Exception as e:
            logging.error(f"Erreur lors de la récupération des contraintes ({table_name}) : {e}")
            # On retourne un DataFrame vide avec les bonnes colonnes pour ne pas faire planter la suite du code
            return pd.DataFrame(columns=[id_col, 'day_of_week', 'start_time', 'end_time', 'priority', 'week_id'])

    # === Fetch helpers ===
    def _fetch_rooms(self) -> pd.DataFrame:
        return pd.read_sql("SELECT id as name, seat_capacity FROM rooms WHERE id NOT IN (17, 18)", self.engine)

    def _fetch_profs(self) -> pd.DataFrame:
        query = (
            """SELECT t.id                                   AS teacher_id,
                      CONCAT(u.first_name, ' ', u.last_name) AS prof_name
               FROM teachers t
                        JOIN users u ON t.user_id = u.id"""
        )
        return pd.read_sql(query, self.engine)

    def _fetch_planning(self, week_id: int) -> pd.DataFrame:
        query_slots = """
                      SELECT s.id, \
                             s.duration, \
                             t.title              AS teaching_title, \
                             p.name               AS promotion_name, \
                             g.name               AS group_name, \
                             sg.name              AS subgroup_name,
                             promo.student_amount AS promo_size, \
                             gr.student_amount    AS group_size, \
                             sub.student_amount   AS subgroup_size, \
                             s.type_id, \
                             s.promotion_id
                      FROM slots s
                               LEFT JOIN teachings t ON s.teaching_id = t.id
                               LEFT JOIN promotions p ON s.promotion_id = p.id
                               LEFT JOIN `groups` g ON s.group_id = g.id
                               LEFT JOIN subgroups sg ON s.subgroup_id = sg.id
                               LEFT JOIN promotions promo ON s.promotion_id = promo.id
                               LEFT JOIN `groups` gr ON s.group_id = gr.id
                               LEFT JOIN subgroups sub ON s.subgroup_id = sub.id  
                      WHERE week_id= %s
                      """
        return pd.read_sql(query_slots, self.engine, params=(week_id,), index_col='id')

    def _fetch_profs_par_slot(self) -> dict:
        query_prof_slot = """
            SELECT s.id AS slot_id, CONCAT(u.first_name, ' ', u.last_name) AS prof_name
            FROM slots_teachers st
            JOIN slots s ON st.slot_id = s.id
            JOIN teachers t ON st.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
        """
        df_prof_slot = pd.read_sql(query_prof_slot, self.engine)
        if df_prof_slot.empty or 'slot_id' not in df_prof_slot.columns:
            return {}
        return df_prof_slot.groupby('slot_id')['prof_name'].apply(list).to_dict()

    def _build_constraints(self, week_id: int, creneaux_par_jour: int):
        df_dispos = self._get_generic_constraints("teacher_constraints", "teacher_id", week_id)
        disponibilites_profs = get_availability_prof_from_unavailable(df_dispos, creneaux_par_jour)

        df_dispos_salles = self._get_generic_constraints("room_constraints", "room_id", week_id)
        disponibilites_salles = get_availability_room_from_unavailable(df_dispos_salles, creneaux_par_jour)

        df_dispos_groupes = self._get_generic_constraints("group_constraints", "group_id", week_id)
        disponibilites_groupes = get_availability_group_from_unavailable(df_dispos_groupes, creneaux_par_jour)

        # MAPPING : Convertir les IDs numériques en noms de groupes
        disponibilites_groupes = self._mapper_ids_vers_noms_groupes(disponibilites_groupes)

        # PROPAGATION : Si un groupe parent est indisponible, tous ses enfants le sont aussi
        disponibilites_groupes = self._propager_indisponibilites_groupes(disponibilites_groupes)

        df_dispos_slots = self._get_generic_constraints("slot_constraints", "slot_id", week_id)
        logging.debug("df_dispos_slots (week %s): %s", week_id, df_dispos_slots)
        disponibilites_slots = get_availability_slot_from_unavailable(df_dispos_slots, creneaux_par_jour)

        return disponibilites_profs, disponibilites_salles, disponibilites_groupes, disponibilites_slots

    def _mapper_ids_vers_noms_groupes(self, disponibilites_groupes: dict) -> dict:
        """
        Convertit les clés numériques (IDs) en noms de groupes (G1, G2, etc.)
        Exemple : {1: {...}} → {G1: {...}}
        """
        # Mapping ID → noms de groupes
        id_to_groupe = {
            1: 'G1',
            2: 'G2',
            3: 'G3',
            4: 'G4',
            5: 'G5',
            6: 'G6',
            7: 'G7',
            8: 'G8',
        }

        logging.info(f"\n=== AVANT MAPPING ===")
        logging.info(f"Clés disponibilités : {list(disponibilites_groupes.keys())}")
        logging.info(f"Contenu : {disponibilites_groupes}\n")

        disponibilites_mappees = {}
        for cle, dispos in disponibilites_groupes.items():
            # Si la clé est un ID numérique, la convertir
            if isinstance(cle, int) and cle in id_to_groupe:
                nouveau_cle = id_to_groupe[cle]
                logging.info(f"   Mapping : ID {cle} → {nouveau_cle}")
                disponibilites_mappees[nouveau_cle] = dispos
            else:
                # Sinon, garder comme c'est (déjà un nom de groupe)
                disponibilites_mappees[cle] = dispos

        logging.info(f"\n=== APRÈS MAPPING ===")
        logging.info(f"Clés disponibilités : {list(disponibilites_mappees.keys())}")
        logging.info(f"Contenu : {disponibilites_mappees}\n")

        return disponibilites_mappees

    def _propager_indisponibilites_groupes(self, disponibilites_groupes: dict) -> dict:
        """
        Propage les indisponibilités des groupes parents à leurs enfants.
        Si BUT1 est indisponible, alors G1, G1A, G1B, G2, G2A, G2B, G3, G3A, G3B le sont aussi.
        """
        # Définir la hiérarchie complète (parents → enfants)
        hierarchie_complete = {
            # BUT -> Groupes
            "BUT1": ["G1", "G2", "G3", "G1A", "G1B", "G2A", "G2B", "G3A", "G3B"],
            "BUT2": ["G4", "G5", "G4A", "G4B", "G5A", "G5B"],
            "BUT3": ["G7", "G8", "G7A", "G7B", "G8A"],
            # Groupes -> Sous-groupes
            "G1": ["G1A", "G1B"],
            "G2": ["G2A", "G2B"],
            "G3": ["G3A", "G3B"],
            "G4": ["G4A", "G4B"],
            "G5": ["G5A", "G5B"],
            "G7": ["G7A", "G7B"],
            "G8": ["G8A"],
        }

        # Pour chaque groupe parent avec indisponibilités
        for parent_groupe, enfants in hierarchie_complete.items():
            if parent_groupe in disponibilites_groupes:
                parent_dispos = disponibilites_groupes[parent_groupe]
                logging.debug(f"Propagation : {parent_groupe} indisponible → {enfants}")

                # Copier les indisponibilités du parent à chaque enfant
                for enfant in enfants:
                    if enfant not in disponibilites_groupes:
                        disponibilites_groupes[enfant] = {}

                    # Fusionner les indisponibilités : intersection (un enfant indisponible à un créneau si le parent l'est)
                    for jour, plages_parent in parent_dispos.items():
                        if jour not in disponibilites_groupes[enfant]:
                            disponibilites_groupes[enfant][jour] = []
                        # Garder les plages du parent (qui servent de restriction)
                        disponibilites_groupes[enfant][jour] = plages_parent

        return disponibilites_groupes

    def load_and_prepare_data(self,week_id:int) -> Dict[str, Any]:
        """Charge les données BDD et prépare le payload pour le modèle."""
        list_amphi_c=[{0: [(11, 23)]},{1: [(0, 7)]},{2: [(0, 7)]},{3: []},{4: [(11, 23)]}] # TODO externaliser
        logging.info("Chargement des données depuis la base de données...")

        jours = 5
        creneaux_par_jour = 23
        slots = [(d, s) for d in range(jours) for s in range(creneaux_par_jour)]
        fenetre_midi = list(range(8, 11))

        # Fetch de base
        df_salles = self._fetch_rooms()
        df_profs_with_id = self._fetch_profs()
        df_planning = self._fetch_planning(week_id)
        profs_par_slot = self._fetch_profs_par_slot()

        # Contraintes
        disponibilites_profs, disponibilites_salles, disponibilites_groupes, disponibilites_slots = self._build_constraints(
            week_id, creneaux_par_jour
        )

        prof_to_teacher_id = dict(zip(df_profs_with_id['prof_name'], df_profs_with_id['teacher_id']))
        profs = df_profs_with_id['prof_name'].tolist()

        cours, duree_cours, taille_groupes, map_groupe_cours = self._build_course_structures(
            df_planning, profs_par_slot, profs
        )
        salles = df_salles.set_index('name')['seat_capacity'].to_dict()

        logging.info("%d cours à planifier.", len(cours))
        logging.info("%d salles et %d professeurs disponibles.", len(salles), len(profs))

        group_to_dispo_key = {
            'BUT1': 1,
            'G1': 1, 'G2': 2, 'G3': 3,
            'G4': 4, 'G5': 5, 'G7': 7, 'G8': 8,
            'G1A': 1, 'G1B': 1,
            'G2A': 2, 'G2B': 2,
            'G3A': 3, 'G3B': 3,
        }

        # Créer l'inverse : map_cours_groupes (cours → groupes)
        map_cours_groupes = {}
        for groupe, cours_list in map_groupe_cours.items():
            for cid in cours_list:
                if cid not in map_cours_groupes:
                    map_cours_groupes[cid] = []
                map_cours_groupes[cid].append(groupe)

        return {
            "jours": jours, "creneaux_par_jour": creneaux_par_jour, "slots": slots, "nb_slots": len(slots),
            "fenetre_midi": fenetre_midi,
            "cours": cours, "duree_cours": duree_cours, "taille_groupes": taille_groupes,
            "map_groupe_cours": map_groupe_cours,
            "map_cours_groupes": map_cours_groupes,
            "salles": salles, "capacites": list(salles.values()), "profs": profs,
            "profs_par_slot": profs_par_slot,
            "all_groups": list(map_groupe_cours.keys()),
            "disponibilites_profs": disponibilites_profs,
            "disponibilites_salles": disponibilites_salles,
            "disponibilites_groupes": disponibilites_groupes,
            "obligations_slots": disponibilites_slots,
            "prof_to_teacher_id": prof_to_teacher_id,
            "liste_amphi_c": list_amphi_c,
            "group_to_dispo_key": group_to_dispo_key
        }

    def get_list_room(self):
        list_room=[]
        query_dispos = """SELECT name FROM rooms """
        df_salles = pd.read_sql(query_dispos, self.engine)
        for i in df_salles['name']:
            list_room.append(i)
        return list_room

    def _build_course_structures(self, df: pd.DataFrame,profs_par_slot: dict, profs: list) -> Tuple:

        cours, duree_cours, taille_groupes, map_groupe_cours = [], {}, {}, {}
        cpt_no_profs = 0
        logging.info("\n=== STRUCTURES DES COURS ===")
        logging.info(f"Total profs disponibles: {len(profs)} = {profs}\n")

        for idx, row in df.iterrows():
            duration_slots = int(row['duration'] * 2)

            if row['type_id'] == 1:  # CM → concerne TOUTE la promotion
                group_name = row['promotion_name']  # "BUT1", "BUT2", etc.
                cid = f"CM_{row['teaching_title']}_{group_name}_s{idx}"
                group_size = row['promo_size']

                # Le CM concerne TOUS les sous-groupes de cette promotion
                affected_groups = [group_name]  # BUT1 lui-même
                if group_name in self.group_map:
                    affected_groups.extend(self.group_map[group_name])

            elif row['type_id'] == 2:  # TD → un seul groupe
                group_name = row['group_name']
                cid = f"TD_{row['teaching_title']}_{group_name}_s{idx}"
                group_size = row['group_size']
                affected_groups = [group_name]

            elif row['type_id'] == 3:  # TP → un seul sous-groupe
                group_name = f"{row['group_name']}{row['subgroup_name']}"
                cid = f"TP_{row['teaching_title']}_{group_name}_s{idx}"
                group_size = row['subgroup_size']
                affected_groups = [group_name]
            elif row['type_id'] == 4:  # SAE → un seul groupe
                group_name = row['group_name']
                if group_name== None:
                    group_name = row['promotion_name']
                affected_groups = [group_name]  # BUT1 lui-même
                group_size = row['group_size']
                if group_name in self.group_map:
                    affected_groups.extend(self.group_map[group_name])
                    group_size = row['promo_size']
                cid = f"SAE_{row['teaching_title']}_{group_name}_s{idx}"
            #TODO gestion Exam
            #TODO gestion Autre -> conférence / Rentrée Autre si nécessaire

            else:
                continue

            # On crée le cours
            profs_autorises = profs_par_slot.get(idx, [])
            indices_profs = [i for i, name in enumerate(profs) if name in profs_autorises]
            if not indices_profs:
                logging.warning("Aucun prof autorisé pour %s", cid)
                profs.append("None_"+str(cpt_no_profs))
                index=profs.index("None_"+str(cpt_no_profs))
                indices_profs = [index]
                logging.debug("indices profs pour %s : %s", cid, indices_profs)
                cpt_no_profs+=1

            profs_noms = [profs[p] for p in indices_profs]
            logging.info(f"Cours {cid}: {len(indices_profs)} profs autorisés = {profs_noms}")

            cours.append({
                "id": cid,
                "groups": affected_groups,
                "allowed_prof_indices": indices_profs
            })
            duree_cours[cid] = duration_slots
            taille_groupes[group_name] = int(group_size) if pd.notna(group_size) else 0
            # On l'ajoute dans TOUS les groupes qu'il concerne
            for g in affected_groups:
                if g not in map_groupe_cours:
                    map_groupe_cours[g] = []
                map_groupe_cours[g].append(cid)
        return cours, duree_cours, taille_groupes, map_groupe_cours

    def convert_courses_dict_to_list_insert(self,courses_dict_list):
        cours_input = []

        for c in courses_dict_list:
            name = c['name']
            day_name = convert_days_int_to_string(c['day'])
            tuple_cours = (
                c['start_hour'],
                name.split('_')[-1][1:],  # type
                c['room'],
                day_name,
            )
            cours_input.append(tuple_cours)
        df_insert = pd.DataFrame(cours_input, columns=['start_hour', 'slot_id', 'room_id','day_of_week'])
        table="edt_slot"
        self.insert_data_with_pandas(df_insert, table)
        return cours_input

    def insert_data_with_pandas(self, df_to_insert, table_name):
        try:
            # Insertion dans la base de données
            # 'if_exists' peut être 'fail', 'replace' ou 'append'
            # 'index=False' pour ne pas insérer l'index du DataFrame comme colonne
            rows_inserted = df_to_insert.to_sql(
                name=table_name,
                con=self.engine,  # Votre moteur de base de données
                if_exists='append',  # Ajoute les lignes à la table existante
                index=False
            )
            logging.info("%s lignes insérées dans la table '%s'.", rows_inserted, table_name)
        except Exception as e:
            logging.error("Erreur lors de l'insertion dans '%s' : %s", table_name, e)
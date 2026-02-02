# ==============================================================================
# CLASSE 2: LE MODÈLE D'OPTIMISATION (TimetableModel)
# ==============================================================================
import logging
from typing import Dict, Any

from ortools.sat.python import cp_model

from function import recup_cours, recup_id_slot_from_str_to_int

logger = logging.getLogger(__name__)


class TimetableModel:
    # Hiérarchie par défaut des groupes : sous-groupe → groupe parent
    DEFAULT_HIERARCHIE_GROUPES = {
        "G1A": "G1", "G1B": "G1",
        "G2A": "G2", "G2B": "G2",
        "G3A": "G3", "G3B": "G3",
        "G4A": "G4", "G4B": "G4",
        "G5A": "G5", "G5B": "G5",
        "G7A": "G7", "G7B": "G7",
        "G8A": "G8",
    }

    def __init__(self, data: Dict[str, Any]):
        self.data = data
        self.model = cp_model.CpModel()
        self._vars = {}
        self.temp = []
        self._ordres_a_forcer = []
        
        # Initialiser la hiérarchie des groupes (paramétrable via data)
        self.hierarchie_groupes = data.get('hierarchie_groupes', self.DEFAULT_HIERARCHIE_GROUPES)

    @staticmethod
    def generer_hierarchie_depuis_noms(groupes: list[str]) -> Dict[str, str]:
        """
        Génère automatiquement la hiérarchie des groupes basée sur les noms.
        Assume que les sous-groupes sont nommés comme 'G1A', 'G1B' etc.
        
        Args:
            groupes: Liste des noms de groupes/sous-groupes
            
        Returns:
            Dict mapping sous-groupes → groupes parents
            
        Example:
            >>> groupes = ['G1', 'G1A', 'G1B', 'G2', 'G2A']
            >>> TimetableModel.generer_hierarchie_depuis_noms(groupes)
            {'G1A': 'G1', 'G1B': 'G1', 'G2A': 'G2'}
        """
        hierarchie = {}
        for groupe in groupes:
            # Chercher les sous-groupes : groupe + lettre (ex: G1A, G1B)
            for autre in groupes:
                if autre.startswith(groupe) and len(autre) > len(groupe):
                    # Vérifier que la différence est juste une lettre à la fin
                    if autre[len(groupe):].isalpha() and len(autre[len(groupe):]) == 1:
                        hierarchie[autre] = groupe
        return hierarchie

    def _est_disponible(self, plages: list, offset: int, duration: int) -> bool:
        """
        Vérifie si une entité (prof, salle, groupe) est disponible pour un cours.
        
        Args:
            plages: Liste des (debut, fin) de disponibilité pour le jour
            offset: Créneau de début du cours
            duration: Durée du cours en créneaux
            
        Returns:
            True si disponible, False sinon
        """
        if not plages:
            return False
        return any(debut <= offset and offset + duration <= fin for debut, fin in plages)

    def _appliquer_contrainte_disponibilite_generique(
        self,
        d: Dict[str, Any],
        entite_type: str,
        dispos: Dict,
        var_dict_key: str,
        index_mapping: Dict = None,
        log_message: str = ""
    ):
        """
        Applique une contrainte de disponibilité générique pour une entité.
        """
        if log_message:
            logger.info(log_message)
        
        if not dispos:
            logger.info("      → Aucune disponibilité spécifique trouvée, skipping.")
            return
        
        logger.debug(f"   DEBUG ({entite_type}) : Clés dispos = {list(dispos.keys())}")
        
        # Créer le mapping prof_name → teacher_id si nécessaire
        prof_to_teacher_id = d.get("prof_to_teacher_id", {}) if entite_type == 'prof' else {}
        
        constraints_appliquees = 0
        for c in d['cours']:
            cid = c['id']
            duration = d['duree_cours'][cid]
            
            for s, (day_idx, offset) in enumerate(d['slots']):
                start_var = self._vars['start'].get((cid, s))
                if start_var is None or offset + duration > d['creneaux_par_jour']:
                    continue
                
                # Déterminer les indices à vérifier selon le type d'entité
                if entite_type == 'prof':
                    # Pour les profs, ne vérifier que les indices autorisés
                    allowed_indices = c.get('allowed_prof_indices', [])
                    indices_to_check = allowed_indices
                    # Mapper prof_name → teacher_id pour la recherche dans dispos
                    id_list = [prof_to_teacher_id.get(d['profs'][idx]) for idx in indices_to_check]
                    
                elif entite_type == 'salle':
                    # Pour les salles, vérifier tous les indices
                    indices_to_check = range(len(d['salles']))
                    if index_mapping:
                        id_list = [index_mapping.get(idx) for idx in indices_to_check]
                    else:
                        id_list = [list(d['salles'].keys())[idx] for idx in indices_to_check]
                    
                elif entite_type == 'groupe':
                    # Pour les groupes, vérifier les groupes associés au cours
                    id_list = d.get('map_cours_groupes', {}).get(cid, [])
                    indices_to_check = range(len(id_list))
                    if id_list:
                        logger.debug(f"   DEBUG (groupe) : Cours {cid} → groupes {id_list}")
                else:
                    logger.warning(f"Type d'entité inconnu: {entite_type}")
                    return
                
                # Vérifier pour chaque index/entité
                for idx, entite_id in zip(indices_to_check, id_list):
                    if not entite_id:
                        logger.debug(f"   DEBUG ({entite_type}) : entite_id vide pour cours {cid}")
                        continue
                    
                    if entite_id not in dispos:
                        if entite_type == 'groupe':
                            logger.debug(f"   DEBUG (groupe) : {entite_id} NOT IN dispos. Clés dispos = {list(dispos.keys())}")
                        continue
                    
                    plages = dispos[entite_id].get(day_idx, [])
                    
                    if not self._est_disponible(plages, offset, duration):
                        # L'entité est indisponible → bloquer la variable
                        if entite_type == 'groupe':
                            # Pour les groupes, on bloque directement le start
                            logger.debug(f"      → Groupe {entite_id} BLOQUÉ pour {cid} slot {s} (jour {day_idx}, offset {offset})")
                            self.model.Add(start_var == False)
                            constraints_appliquees += 1
                            break  # Un seul groupe indisponible suffit
                        else:
                            # Pour prof/salle, on utilise AddBoolOr
                            var = self._vars[var_dict_key].get((cid, idx))
                            if var is not None:
                                self.model.AddBoolOr([start_var.Not(), var.Not()])
                                constraints_appliquees += 1
        
        logger.debug(f"   DEBUG ({entite_type}) : {constraints_appliquees} contraintes appliquées")

    def build_model(self):
        logger.info("2. Construction du modèle d'optimisation...")
        self._create_decision_variables()
        self._add_linking_constraints()
        self._add_structural_constraints()
        self.appliquer_ordre_cm_td_tp()  # ← ICI on les APPLIQUE (variables existent !)
        self._define_objective_function()  # Déplacé avant la résolution
        logger.info("   -> Modèle construit.")

    def solve(self, max_time_seconds: int = 600) -> Dict[str, Any]:
        logger.info("\n3. Lancement de la résolution...")
        solver = cp_model.CpSolver()
        solver.parameters.max_time_in_seconds = max_time_seconds
        solver.parameters.num_search_workers = 8
        status = solver.Solve(self.model)
        logger.info(f"   -> Résolution terminée avec le statut : {solver.StatusName(status)}")
        return {"status": status, "solver": solver,
                "vars": self._vars if status in (cp_model.OPTIMAL, cp_model.FEASIBLE) else None}

    def _create_decision_variables(self):
        d = self.data
        self._vars.update({'start': {}, 'occupe': {}, 'y_salle': {}, 'z_prof': {}})
        for c in d['cours']:
            cid, duration = c['id'], d['duree_cours'][c['id']]#[cid]
            for s, (day, offset) in enumerate(d['slots']):
                chevauche_midi = any(offset + i in d['fenetre_midi'] for i in range(duration))
                if offset + duration <= d['creneaux_par_jour'] and not chevauche_midi:
                    self._vars['start'][cid, s] = self.model.NewBoolVar(f"start_{cid}_{s}")
                else:
                    self._vars['start'][cid, s] = None
            for t in range(d['nb_slots']): self._vars['occupe'][cid, t] = self.model.NewBoolVar(f"occupe_{cid}_{t}")
            for r in range(len(d['salles'])): self._vars['y_salle'][cid, r] = self.model.NewBoolVar(f"y_salle_{cid}_{r}")
            for p in range(len(d['profs'])): self._vars['z_prof'][cid, p] = self.model.NewBoolVar(f"z_prof_{cid}_{p}")

    def _add_linking_constraints(self):
        d = self.data
        logger.info("\n=== DIAGNOSTIC DES PROFESSEURS ===")
        logger.info(f"Total de profs disponibles: {len(d['profs'])}")
        logger.info(f"Profs: {d['profs']}\n")
        
        for c in d['cours']:
            cid = c['id']
            valid_starts = [v for v in self._vars['start'].values() if v is not None and v.Name().startswith(f"start_{cid}")]
            self.model.Add(sum(valid_starts) == 1)
            self.model.Add(sum(self._vars['y_salle'][cid, r] for r in range(len(d['salles']))) == 1)
            
            # Forcer TOUS les profs autorisés à être affectés à ce cours
            allowed = c.get("allowed_prof_indices", list(range(len(d['profs']))))
            profs_noms = [d['profs'][p] for p in allowed]
            logger.info(f"Cours {cid}: {len(allowed)} profs autorisés = {profs_noms}")

            if allowed:
                for p in allowed:
                    # Si le cours a ce prof dans allowed, on impose z_prof=1 ⇒ le prof est occupé
                    self.model.Add(self._vars['z_prof'][cid, p] == 1)
                # Et on bloque explicitement tous les autres
                for p in range(len(d['profs'])):
                    if p not in allowed:
                        self.model.Add(self._vars['z_prof'][cid, p] == 0)
            for t, (day_t, offset_t) in enumerate(d['slots']):
                covering_starts = [self._vars['start'][cid, s] for s, (day_s, offset_s) in enumerate(d['slots']) if
                                   self._vars['start'][cid, s] is not None and day_s == day_t and offset_s <= offset_t < offset_s +
                                   d['duree_cours'][cid]]
                if covering_starts:
                    self.model.Add(sum(covering_starts) == self._vars['occupe'][cid, t])
                else:
                    self.model.Add(self._vars['occupe'][cid, t] == 0)

    def _add_structural_constraints(self):
        d = self.data
        # 1. Contraintes salles
        self.contrainte_salle(d)
        # 2. Contraintes professeurs
        self.contrainte_professeurs(d)

        # 3. CONTRAINTE ÉTUDIANT
        self.contrainte_etudiant(d)
        # =================================================================
        # 4. CONTRAINTE HIÉRARCHIQUE : les sous-groupes bloquent leur groupe parent
        # =================================================================
        self.contrainte_hierarchique(d)
        self.contrainte_disponibilites_professeurs(d)
        self.contrainte_disponibilites_groupes(d)
        self.contrainte_disponibilites_salles_generalisee(d)
        #self.contrainte_disponibilites_amphi_c(d)
        #test
        self.contrainte_ordre_cm_td_tp(d)
        self.appliquer_ordre_cm_td_tp()
        self.penaliser_fin_tardive(d, cout_penalite=500, limite_offset_fin=20)
        penaliser_proximite_pause_midi=False
        if penaliser_proximite_pause_midi:
            self.penaliser_proximite_pause_midi(d, cout_par_creneau=50)
        self.contrainte_disponibilites_cour_heure(d)


    def contrainte_hierarchique(self, d: dict[str, Any]):
        logger.info("   -> Ajout des contraintes hiérarchiques (sous-groupes ↔ groupe parent)")

        for sous_groupe, groupe_parent in self.hierarchie_groupes.items():
            if sous_groupe not in d['map_groupe_cours'] or groupe_parent not in d['map_groupe_cours']:
                continue

            logger.debug(f"      → {sous_groupe} bloque {groupe_parent} (et vice versa)")

            for t in range(d['nb_slots']):
                # Tous les cours du sous-groupe
                cours_sous = [self._vars['occupe'][cid, t]
                              for cid in d['map_groupe_cours'][sous_groupe]
                              if (cid, t) in self._vars['occupe']]
                # Tous les cours du groupe parent
                cours_parent = [self._vars['occupe'][cid, t]
                                for cid in d['map_groupe_cours'][groupe_parent]
                                if (cid, t) in self._vars['occupe']]

                # On retire les cours du sous-groupe pour éviter double comptage
                cours_parent_clean = [v for v in cours_parent
                                      if not any(
                        v.Name().startswith(f"occupe_{cid}") for cid in d['map_groupe_cours'][sous_groupe])]

                all_concerned = cours_sous + cours_parent_clean
                if all_concerned:
                    self.model.Add(sum(all_concerned) <= 1)

    def contrainte_etudiant(self, d: dict[str, Any]):
        for group_name, course_list in d['map_groupe_cours'].items():
            if len(course_list) > 1:  # seulement si risque de chevauchement
                for t in range(d['nb_slots']):
                    active = [self._vars['occupe'][cid, t]
                              for cid in course_list
                              if (cid, t) in self._vars['occupe']]
                    if active:
                        self.model.Add(sum(active) <= 1)

    def contrainte_professeurs(self, d: dict[str, Any]):
        logger.info("\n=== CONTRAINTE PROFESSEURS (non-chevauchement) - OPTIMISÉ ===")
        conflit_count = 0
        profs_multi_cours = {}
        vars_created = 0
        
        # Pré-calculer les cours par prof pour éviter de recalculer à chaque créneau
        cours_par_prof = {p_idx: [] for p_idx in range(len(d['profs']))}
        for c in d['cours']:
            for p_idx in c.get('allowed_prof_indices', []):
                cours_par_prof[p_idx].append(c['id'])
        
        for t in range(d['nb_slots']):
            for p_idx in range(len(d['profs'])):
                courses_for_prof = cours_par_prof[p_idx]
                
                # OPTIMISATION : Sauter si le prof n'a qu'un seul cours ou aucun
                if len(courses_for_prof) <= 1:
                    continue
                
                p_vars = []
                for cid in courses_for_prof:
                    z = self.model.NewBoolVar(f"zact_c{cid}_t{t}_p{p_idx}")
                    self.model.AddMultiplicationEquality(z, [
                        self._vars['occupe'][cid, t],
                        self._vars['z_prof'][cid, p_idx]
                    ])
                    p_vars.append(z)
                    vars_created += 1
                
                prof_name = d['profs'][p_idx]
                if prof_name not in profs_multi_cours:
                    profs_multi_cours[prof_name] = courses_for_prof
                conflit_count += 1
                    
                self.model.Add(sum(p_vars) <= 1)
        
        logger.info(f"Variables intermédiaires créées: {vars_created} (optimisé vs {d['nb_slots'] * len(d['profs']) * len(d['cours'])} max)")
        logger.info(f"Nombre de (prof, créneau) avec risque de conflit: {conflit_count}")
        for prof_name, courses in profs_multi_cours.items():
            logger.info(f"  Prof '{prof_name}' assigné à {len(set(courses))} cours: {set(courses)}")

    def contrainte_salle(self, d: dict[str, Any]):
        logger.info("=== CONTRAINTE SALLES (non-chevauchement) - OPTIMISÉ ===")
        vars_created = 0
        
        for t in range(d['nb_slots']):
            for r_idx in range(len(d['salles'])):
                q_vars = []
                for c in d['cours']:
                    cid = c['id']
                    # OPTIMISATION : Ne créer la variable que si le cours peut réellement occuper ce créneau
                    if (cid, t) not in self._vars['occupe']:
                        continue
                    
                    q = self.model.NewBoolVar(f"q_c{cid}_t{t}_r{r_idx}")
                    self.model.AddMultiplicationEquality(q, [
                        self._vars['occupe'][cid, t],
                        self._vars['y_salle'][cid, r_idx]
                    ])
                    q_vars.append(q)
                    vars_created += 1
                
                if q_vars:  # Seulement ajouter la contrainte si nécessaire
                    self.model.Add(sum(q_vars) <= 1)
        
        logger.info(f"   → {vars_created} variables intermédiaires créées pour contraintes salles")

    def contrainte_disponibilites_professeurs(self, d):
        """Applique les contraintes de disponibilité horaire des professeurs."""
        dispos = d.get('disponibilites_profs', {})
        
        self._appliquer_contrainte_disponibilite_generique(
            d, 'prof', dispos, 'z_prof',
            log_message="   -> Application des disponibilités horaires des professeurs"
        )

    def contrainte_disponibilites_salles(self, d):
        """Applique les contraintes de disponibilité horaire des salles."""
        dispos = d.get('disponibilites_salles', {})
        
        # Créer le mapping index_physique → salle_id
        salle_id_map = list(d['salles'].keys()) if d['salles'] else []
        index_mapping = {i: salle_id_map[i] for i in range(len(salle_id_map))}
        
        self._appliquer_contrainte_disponibilite_generique(
            d, 'salle', dispos, 'y_salle',
            index_mapping=index_mapping,
            log_message="   -> Application des disponibilités horaires des salles"
        )

    def contrainte_disponibilites_groupes(self, d: dict[str, Any]):
        """Applique les contraintes de disponibilité horaire pour les groupes d'étudiants."""
        dispos = d.get('disponibilites_groupes', {})
        
        self._appliquer_contrainte_disponibilite_generique(
            d, 'groupe', dispos, None,
            log_message="   -> Application des disponibilités horaires des groupes"
        )

    def contrainte_disponibilites_salles_generalisee(self, d):
        """Applique les contraintes de disponibilité horaire des salles (version robuste générique)."""
        dispos = d.get('disponibilites_salles', {})
        logger.debug("dispos salles : %s", dispos)
        
        # Créer le mapping index_physique → salle_id
        salle_id_map = list(d['salles'].keys()) if d['salles'] else []
        index_mapping = {i: salle_id_map[i] for i in range(len(salle_id_map))}
        
        self._appliquer_contrainte_disponibilite_generique(
            d, 'salle', dispos, 'y_salle',
            index_mapping=index_mapping,
            log_message="   -> Application générale des disponibilités horaires des salles (Robuste)"
        )

    def contrainte_disponibilites_cour_heure(self, d):
        logger.info("   -> Application des horaires obligatoires pour les slots/salles")
        # On utilise 'obligations_slots' pour clarifier l'intention
        obligations = d.get('obligations_slots', {})

        if not obligations:
            logger.info("      → Aucune contrainte d'horaire obligatoire spécifique trouvée, skipping.")
            return

        # 1. Itérer sur TOUS les SLOTS/SALLES qui ont des contraintes d'horaire obligatoires
        for slot_id, contraintes_par_jour in obligations.items():  # Le slot_id est la clé du dictionnaire (e.g., 23000000)
            # 2. Itérer sur tous les cours
            for c in d['cours']:
                cid = c['id']
                id_slot_cour = recup_id_slot_from_str_to_int(cid)  # La fonction qui associe le cours à son slot/salle
                # --- MODIFICATION CLÉ 1 : Cibler UNIQUEMENT les cours associés à ce slot ---
                # Si le cours n'est pas censé utiliser ce slot/salle, on passe au suivant.
                if id_slot_cour != slot_id:
                    continue

                duration = d['duree_cours'][cid]

                # 3. Itérer sur tous les créneaux de temps (S, jour, offset)
                for s, (day_idx, offset) in enumerate(d['slots']):
                    start_var = self._vars['start'].get((cid, s))
                    if start_var is None or offset + duration > d['creneaux_par_jour']:
                        continue
                    # 4. Déterminer si cet horaire (jour, offset) est OBLIGATOIRE
                    creneaux_obligatoires_jour = contraintes_par_jour.get(day_idx, [])
                    # Le cours DOIT commencer à cette heure s'il est affecté à ce slot.
                    # Dans le cas où un jour n'a aucune obligation, le cours n'est PAS autorisé ce jour-là.
                    # C'est une interprétation stricte de "obligatoire".

                    est_horaire_obligatoire = False
                    for debut, fin in creneaux_obligatoires_jour:
                        # Vérifier si le cours (offset, offset + duration) est PARFAITEMENT ÉGAL
                        # à l'un des créneaux obligatoires (debut, fin).
                        # Pour être "obligatoire", on peut exiger une correspondance exacte.
                        # Si c'est juste "doit commencer dans la plage", utiliser :
                        # if debut <= offset and offset + duration <= fin:

                        # Pour l'horaire OBLIGATOIRE, je recommande une correspondance stricte:
                        if debut == offset and fin == offset + duration:
                            est_horaire_obligatoire = True
                            break

                    # --- MODIFICATION CLÉ 2 : Logique Inversée ---
                    # Si le cours est affecté à ce slot (déjà vérifié par 'id_slot_cour == slot_id')
                    # MAIS que l'horaire (s) N'EST PAS l'un des horaires obligatoires
                    if not est_horaire_obligatoire:
                        # Nous BLOQUONS le démarrage du cours à ce slot/créneau.
                        # Contrainte : start(C, S) est faux
                        self.model.AddBoolOr([start_var.Not()])
                        # print(f"BLOQUÉ: Cours {cid} DOIT utiliser slot {slot_id} mais l'horaire {s} n'est pas obligatoire.")

    def contrainte_disponibilites_amphi_c(self, d):
        logger.info("   -> Application des disponibilités de l'Amphi C (version ROBUSTE)")

        liste_amphi_c = d.get("liste_amphi_c")
        if not liste_amphi_c:
            return

        # Trouver l'indice de l'Amphi C
        try:
            amphi_c_idx = next(i for i, name in enumerate(d['salles']) if name == "AmphiC" or name == 16)
            logger.info(f"      → Amphi C trouvé → indice {amphi_c_idx}")
        except StopIteration:
            logger.warning("      → Amphi C non trouvé dans les salles")
            return

        for c in d['cours']:
            cid = c['id']
            duration = d['duree_cours'][cid]

            for s, (day_idx, offset) in enumerate(d['slots']):
                start_var = self._vars['start'].get((cid, s))
                if start_var is None:
                    continue
                if offset + duration > d['creneaux_par_jour']:
                    continue

                y_amphi = self._vars['y_salle'][(cid, amphi_c_idx)]

                # Récupérer les plages autorisées ce jour
                plages_jour = liste_amphi_c[day_idx].get(day_idx, [])

                # CAS 1 : pas dispo du tout ce jour → interdit si on veut l'Amphi C
                if not plages_jour:
                    self.model.AddBoolOr([start_var.Not(), y_amphi.Not()])
                    continue

                # CAS 2 : vérifier que le cours rentre dans une plage
                rentre_dans_plage = False
                for debut, fin in plages_jour:
                    if debut <= offset and offset + duration <= fin:
                        rentre_dans_plage = True
                        break

                if not rentre_dans_plage:
                    self.model.AddBoolOr([start_var.Not(), y_amphi.Not()])

    def contrainte_ordre_cm_td_tp(self, d):
        logger.info("   → FORÇAGE ORDRE CM → TD → TP : VERSION QUI MARCHE VRAIMENT")

        # On va extraire proprement le nom de la matière (tout entre le type et le _sXXXXX final)
        cours_par_matiere = {}

        for c in d['cours']:
            cid = c['id']

            # On enlève le dernier élément (_s12345) et on reconstruit la clé matière + groupe
            # Ex: "Sensibilisation à la programmation multimédia BUT3"
            typ,matiere = recup_cours(cid)
            if matiere not in cours_par_matiere:
                cours_par_matiere[matiere] = {"CM": [], "TD": [], "TP": []}
            if typ == "CM":
                cours_par_matiere[matiere]["CM"] = cid
            elif typ == "TD":
                cours_par_matiere[matiere]["TD"].append(cid)
            elif typ == "TP":
                cours_par_matiere[matiere]["TP"].append(cid)

        # Stocke pour application plus tard
        ordres = []
        for key, cours in cours_par_matiere.items():
            cm = cours["CM"]
            for td in cours["TD"]:
                if cm:
                    ordres.append((cm, td))
            for tp in cours["TP"]:
                if cm:
                    ordres.append((cm, tp))
                for td in cours["TD"]:
                    ordres.append((td, tp))
        self._ordres_a_forcer = ordres
        logger.info(f"      → {len(ordres)} relations d'ordre détectées et prêtes (CM→TD→TP)")

    def appliquer_ordre_cm_td_tp(self):
        logger.debug("ordre : %s", self._ordres_a_forcer)
        if not hasattr(self, '_ordres_a_forcer') or not self._ordres_a_forcer:
            logger.debug("      → Aucune contrainte d'ordre à appliquer")
            return
        logger.info(f"   → APPLICATION DES {len(self._ordres_a_forcer)} CONTRAINTES D'ORDRE (CM avant TD avant TP) - OPTIMISÉ")
        total_ajoutees = 0

        # OPTIMISATION : Pré-calculer les starts par cours pour éviter le parcours répété
        starts_par_cours = {}
        for (cid, s), var in self._vars['start'].items():
            if var is not None:
                if cid not in starts_par_cours:
                    starts_par_cours[cid] = []
                starts_par_cours[cid].append((s, var))

        for cid_avant, cid_apres in self._ordres_a_forcer:
            starts_avant = starts_par_cours.get(cid_avant, [])
            starts_apres = starts_par_cours.get(cid_apres, [])

            for s1, v1 in starts_avant:
                for s2, v2 in starts_apres:
                    if s1 >= s2:
                        self.model.AddBoolOr([v1.Not(), v2.Not()])
                        total_ajoutees += 1

        logger.info(f"      → {total_ajoutees} contraintes d'interdiction ajoutées → ORDRE FORCÉ À 100%")

    def penaliser_fin_tardive(self, d, cout_penalite: int = 500, limite_offset_fin: int = 20):
        """
        Crée des variables de pénalité booléennes (penalty_late_end) pour tout cours (C)
        qui, s'il démarre à un slot (S), finit après la limite_offset_fin.
        Ces variables seront ajoutées à l'objectif de minimisation.
        """
        logger.info(
            f"   -> Application de la préférence : Pénaliser les fins après le slot {limite_offset_fin} (Coût: {cout_penalite})")

        self.penalites_fin_tardive = []  # Liste pour stocker les variables de pénalité

        for c in d['cours']:
            cid = c['id']
            duration = d['duree_cours'][cid]

            for s, (day_idx, offset) in enumerate(d['slots']):

                start_var = self._vars['start'].get((cid, s))
                if start_var is None:
                    continue

                end_offset = offset + duration

                # Si l'heure de fin dépasse la limite (i.e., finit au slot 21 ou après)
                if end_offset > limite_offset_fin:
                    # Créer une variable booléenne qui est VRAIE si la pénalité est appliquée
                    b_late_end = self.model.NewBoolVar(f'penalty_late_end_{cid}_{s}')

                    # Contrainte d'implication :
                    # Si start(C, S) est VRAI, alors b_late_end DOIT être VRAI
                    # start(C, S) => b_late_end
                    # L'écriture AddBoolOr([start_var.Not(), b_late_end]) est équivalente à l'implication.
                    self.model.AddImplication(start_var, b_late_end)

                    # Stocker la pénalité. On stocke le terme (variable * poids)
                    self.penalites_fin_tardive.append(b_late_end * cout_penalite)

        logger.info(f"      → {len(self.penalites_fin_tardive)} départs de cours tardifs potentiels détectés.")

    def penaliser_proximite_pause_midi(self, d, cout_par_creneau: int = 50, seuil_distance: int = 3):
        """
        Ajoute une pénalité linéaire pour favoriser des cours proches de la pause 12h00-13h30.
        Optimisé : ne pénalise que les cours éloignés de plus de seuil_distance créneaux.
        """
        fenetre_midi = d.get('fenetre_midi', [])
        if not fenetre_midi:
            logger.info("   -> Pause midi non définie, contrainte souple ignorée.")
            self.penalites_pause_midi = []
            return

        midi_start = min(fenetre_midi)
        midi_end = max(fenetre_midi) + 1  # fin exclusive

        logger.info(
            f"   -> Application de la préférence : cours proches de la pause {midi_start}-{midi_end} (Coût/créneau: {cout_par_creneau}, Seuil: {seuil_distance})")

        self.penalites_pause_midi = []
        penalites_count = 0

        for c in d['cours']:
            cid = c['id']
            duration = d['duree_cours'][cid]

            for s, (day_idx, offset) in enumerate(d['slots']):
                start_var = self._vars['start'].get((cid, s))
                if start_var is None:
                    continue

                end_offset = offset + duration

                # Calculer la distance à la pause midi
                if end_offset <= midi_start:
                    distance = midi_start - end_offset
                elif offset >= midi_end:
                    distance = offset - midi_end
                else:
                    # Ce cas ne devrait pas arriver car chevauche_midi est interdit
                    distance = 0

                # OPTIMISATION : Ne pénaliser que si distance > seuil
                if distance > seuil_distance:
                    self.penalites_pause_midi.append(start_var * (distance * cout_par_creneau))
                    penalites_count += 1
        
        logger.info(f"      → {penalites_count} pénalités de proximité pause midi créées (vs {len(d['cours']) * len(d['slots'])} possibles).")
    def _define_objective_function(self):
        """Définit les contraintes souples et l'objectif de minimisation."""
        d = self.data
        penalites_capacite = []

        # TRANSFORMATION DE LA CONTRAINTE DE CAPACITÉ EN CONTRAINTE SOUPLE
        logger.info("   -> Application de la contrainte de capacité en mode 'souple'.")
        for c in d['cours']:
            cid, group_name = c['id'], c['groups'][0]
            taille_groupe = d['taille_groupes'].get(group_name, 0)

            for r_idx, capacite_salle in enumerate(d['capacites']):
                if taille_groupe > capacite_salle:
                    # Ce cours ne devrait pas être dans cette salle.
                    # On crée une variable de pénalité.
                    penalite = self.model.NewBoolVar(f"penalite_capacite_{cid}_salle_{r_idx}")

                    # Si le cours est assigné à cette salle (y_salle == 1), la pénalité doit être de 1.
                    self.model.Add(self._vars['y_salle'][cid, r_idx] == 1).OnlyEnforceIf(penalite)
                    self.model.Add(self._vars['y_salle'][cid, r_idx] == 0).OnlyEnforceIf(penalite.Not())

                    penalites_capacite.append(penalite)

        self._vars['penalites_capacite'] = penalites_capacite
        
        # --- Récupération de toutes les pénalités ---
        penalites_tardives = getattr(self, 'penalites_fin_tardive', [])
        penalites_pause_midi = getattr(self, 'penalites_pause_midi', [])
        penalites_trous = getattr(self, 'penalites_trous', [])

        # --- Calcul de l'objectif total (Minimisation) - UNE SEULE FOIS ---
        # 1. Pénalités de capacité (poids très élevé - hard constraint)
        obj_capacite = sum(penalites_capacite) * 1000000
        
        # 2. Pénalités de fin tardive (déjà pondérées par cout_penalite)
        obj_tardif = sum(penalites_tardives)
        
        # 3. Pénalités de proximité pause midi
        obj_pause_midi = sum(penalites_pause_midi)
        
        # 4. Pénalités de trous (si disponible)
        obj_trous = sum(penalites_trous) * 100 if penalites_trous else 0

        # Objectif final unique
        total_obj = obj_capacite + obj_tardif + obj_pause_midi + obj_trous
        
        logger.info(f"   -> Objectif : Capacité: {len(penalites_capacite)}, Tardif: {len(penalites_tardives)}, Pause: {len(penalites_pause_midi)}")
        self.model.Minimize(total_obj)

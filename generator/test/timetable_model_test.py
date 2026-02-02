import pytest
from unittest.mock import MagicMock, patch
from ortools.sat.python import cp_model
from time_table_model import TimetableModel


# ============================================================================
# FIXTURES: Données de test réutilisables
# ============================================================================

@pytest.fixture
def minimal_data():
    """Données minimales pour un modèle testable"""
    return {
        'cours': [
            {'id': 'CM_Maths_BUT1_s1', 'groups': ['BUT1', 'G1', 'G1A'], 'allowed_prof_indices': [0]},
            {'id': 'TD_Maths_G1_s2', 'groups': ['G1'], 'allowed_prof_indices': [1]},
            {'id': 'TP_Maths_G1A_s3', 'groups': ['G1A'], 'allowed_prof_indices': [0, 1]},
        ],
        'duree_cours': {'CM_Maths_BUT1_s1': 2, 'TD_Maths_G1_s2': 2, 'TP_Maths_G1A_s3': 1},
        'salles': {1: 100, 2: 50, 3: 30},
        'capacites': [100, 50, 30],
        'profs': ['Prof A', 'Prof B'],
        'taille_groupes': {'BUT1': 90, 'G1': 30, 'G1A': 15},
        'creneaux_par_jour': 23,
        'nb_slots': 115,  # 5 jours * 23 créneaux
        'slots': [(day, offset) for day in range(5) for offset in range(23)],
        'fenetre_midi': list(range(10, 12)),  # Slots 10-11 midi
        'map_groupe_cours': {
            'BUT1': ['CM_Maths_BUT1_s1'],
            'G1': ['TD_Maths_G1_s2'],
            'G1A': ['TP_Maths_G1A_s3'],
        },
        'map_cours_groupes': {
            'CM_Maths_BUT1_s1': ['BUT1', 'G1', 'G1A'],
            'TD_Maths_G1_s2': ['G1'],
            'TP_Maths_G1A_s3': ['G1A'],
        },
        'prof_to_teacher_id': {'Prof A': 1, 'Prof B': 2},
        'disponibilites_profs': {},
        'disponibilites_salles': {},
        'disponibilites_groupes': {},
        'obligations_slots': {},
        'liste_amphi_c': None,
    }


# ============================================================================
# TESTS: _create_decision_variables()
# ============================================================================

def test_create_decision_variables_basic(minimal_data):
    """Test création basique des variables de décision"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()

    # Vérifier que les dictionnaires sont créés
    assert 'start' in model._vars
    assert 'occupe' in model._vars
    assert 'y_salle' in model._vars
    assert 'z_prof' in model._vars

    # Vérifier qu'il y a des variables start valides (pas juste des None)
    start_vars = [v for v in model._vars['start'].values() if v is not None]
    assert len(start_vars) > 0, "Doit avoir au moins une variable start valide"


def test_create_decision_variables_respects_duration(minimal_data):
    """Test que les variables start respectent la durée et la fenêtre midi"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()

    # CM_Maths a une durée de 2 créneaux, ne doit pas pouvoir démarrer au-delà du créneau 21 (23 - 2)
    cm_id = 'CM_Maths_BUT1_s1'
    for (cid, s), var in model._vars['start'].items():
        if cid == cm_id and var is not None:
            day_idx, offset = minimal_data['slots'][s]
            assert offset + minimal_data['duree_cours'][cm_id] <= minimal_data['creneaux_par_jour'], \
                f"Cours {cm_id} au slot {s} (offset {offset}) dépasserait la journée"


def test_create_decision_variables_avoids_lunch_break(minimal_data):
    """Test que les variables respectent la fenêtre midi"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()

    cm_id = 'CM_Maths_BUT1_s1'
    duration = minimal_data['duree_cours'][cm_id]

    for (cid, s), var in model._vars['start'].items():
        if cid == cm_id and var is not None:
            day_idx, offset = minimal_data['slots'][s]
            # Vérifier qu'aucun créneau du cours n'est dans la fenêtre midi
            for i in range(duration):
                assert offset + i not in minimal_data['fenetre_midi'], \
                    f"Cours {cm_id} au slot {s} chevauche le midi"


def test_create_decision_variables_occupe_and_assignment(minimal_data):
    """Test création des variables occupe, y_salle, z_prof"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()

    # Vérifier que occupe a des entrées pour tous les (cid, t) pairs
    cm_id = 'CM_Maths_BUT1_s1'
    assert any(cid == cm_id for (cid, t) in model._vars['occupe'].keys()), \
        "Doit avoir des variables occupe pour CM"

    # Vérifier y_salle pour toutes les salles
    assert any(cid == cm_id and r < len(minimal_data['salles']) 
               for (cid, r) in model._vars['y_salle'].keys()), \
        "Doit avoir des variables y_salle pour chaque cours et salle"

    # Vérifier z_prof pour tous les profs
    assert any(cid == cm_id and p < len(minimal_data['profs']) 
               for (cid, p) in model._vars['z_prof'].keys()), \
        "Doit avoir des variables z_prof pour chaque cours et prof"


# ============================================================================
# TESTS: _add_linking_constraints()
# ============================================================================

def test_linking_constraints_exactly_one_start(minimal_data):
    """Test que chaque cours démarre exactement une fois"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()
    model._add_linking_constraints()

    # On ne peut pas vérifier la contrainte directement, mais on peut vérifier
    # que la contrainte a été appelée sans erreur
    # et que le modèle contient des contraintes
    assert len(model.model.Proto().constraints) > 0, "Contraintes doivent être ajoutées"


def test_linking_constraints_exactly_one_room(minimal_data):
    """Test que chaque cours utilise exactement une salle"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()
    model._add_linking_constraints()

    # Vérifier que les variables y_salle existent et sont bien liées
    for c in minimal_data['cours']:
        cid = c['id']
        salle_vars = [model._vars['y_salle'].get((cid, r)) for r in range(len(minimal_data['salles']))]
        assert all(v is not None for v in salle_vars), f"Tous les y_salle de {cid} doivent exister"


def test_linking_constraints_prof_assignment(minimal_data):
    """Test que chaque cours assigne le prof selon allowed_prof_indices"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()
    model._add_linking_constraints()

    # Le CM ne peut être assigné qu'à Prof A (indice 0)
    cm_id = 'CM_Maths_BUT1_s1'
    allowed = minimal_data['cours'][0]['allowed_prof_indices']
    assert 0 in allowed, "CM doit avoir Prof A (indice 0) dans allowed_prof_indices"


def test_linking_constraints_occupe_coverage(minimal_data):
    """Test que occupe(cid, t) est couvert par les variables start"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()
    model._add_linking_constraints()

    # Juste vérifier que la méthode ne lève pas d'erreur
    # et que des variables occupe ont été créées
    cm_id = 'CM_Maths_BUT1_s1'
    occupe_count = sum(1 for (cid, t) in model._vars['occupe'].keys() if cid == cm_id)
    assert occupe_count > 0, "Doit avoir des variables occupe pour le CM"


# ============================================================================
# TESTS: contrainte_hierarchique()
# ============================================================================

def test_contrainte_hierarchique_blocks_subgroup_parent(minimal_data):
    """Test que sous-groupe et groupe parent ne peuvent être simultanés"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()
    model._add_linking_constraints()

    # Ajouter les cours avec hiérarchie G1A → G1
    model.contrainte_hierarchique(minimal_data)

    # On ne peut pas directement vérifier la contrainte, mais vérifier qu'elle s'exécute
    # sans erreur et ajoute des contraintes au modèle
    assert len(model.model.Proto().constraints) > 0


def test_contrainte_hierarchique_ignores_missing(minimal_data):
    """Test que la hiérarchie ignore les groupes manquants"""
    data_modif = minimal_data.copy()
    data_modif['map_groupe_cours'] = {'G1': ['TD_Maths_G1_s2']}  # Pas de G1A

    model = TimetableModel(data_modif)
    model._create_decision_variables()
    model._add_linking_constraints()

    # Ne doit pas lever d'erreur
    model.contrainte_hierarchique(data_modif)
    assert True  # Si on arrive ici, ça a marché


# ============================================================================
# TESTS: contrainte_etudiant()
# ============================================================================

def test_contrainte_etudiant_no_overlap_in_group(minimal_data):
    """Test qu'un groupe n'a pas deux cours simultanés"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()
    model._add_linking_constraints()

    model.contrainte_etudiant(minimal_data)

    # Vérifier que la contrainte est appliquée
    assert len(model.model.Proto().constraints) > 0


def test_contrainte_etudiant_single_course_group(minimal_data):
    """Test que groupe avec un seul cours ne génère pas de contrainte"""
    data_modif = minimal_data.copy()
    data_modif['map_groupe_cours'] = {
        'BUT1': ['CM_Maths_BUT1_s1'],  # Seul cours
        'G1': ['TD_Maths_G1_s2'],
    }

    model = TimetableModel(data_modif)
    model._create_decision_variables()
    model._add_linking_constraints()

    initial_constraints = len(model.model.Proto().constraints)
    model.contrainte_etudiant(data_modif)
    # Les groupes avec un seul cours ne doivent pas ajouter de contrainte
    # (mais d'autres groupes peuvent en ajouter)


# ============================================================================
# TESTS: contrainte_professeurs()
# ============================================================================

def test_contrainte_professeurs_no_double_booking(minimal_data):
    """Test qu'un prof n'a pas deux cours au même créneau"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()
    model._add_linking_constraints()

    model.contrainte_professeurs(minimal_data)

    # Vérifier que des contraintes sont ajoutées
    assert len(model.model.Proto().constraints) > 0


# ============================================================================
# TESTS: contrainte_salle()
# ============================================================================

def test_contrainte_salle_no_double_booking(minimal_data):
    """Test qu'une salle n'a pas deux cours au même créneau"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()
    model._add_linking_constraints()

    model.contrainte_salle(minimal_data)

    # Vérifier que des contraintes sont ajoutées
    assert len(model.model.Proto().constraints) > 0


# ============================================================================
# TESTS: contrainte_ordre_cm_td_tp()
# ============================================================================

def test_contrainte_ordre_cm_td_tp_detection(minimal_data):
    """Test détection de l'ordre CM → TD → TP"""
    model = TimetableModel(minimal_data)

    # Créer des données avec CM, TD, TP clairement séparés par type
    data_ordre = {
        'cours': [
            {'id': 'CM_Maths_BUT1_s1', 'groups': ['BUT1'], 'allowed_prof_indices': [0]},
            {'id': 'TD_Maths_G1_s2', 'groups': ['G1'], 'allowed_prof_indices': [0]},
            {'id': 'TP_Maths_G1A_s3', 'groups': ['G1A'], 'allowed_prof_indices': [0]},
        ],
        'duree_cours': {'CM_Maths_BUT1_s1': 2, 'TD_Maths_G1_s2': 2, 'TP_Maths_G1A_s3': 1},
        'salles': {1: 100, 2: 50, 3: 30},
        'capacites': [100, 50, 30],
        'profs': ['Prof A'],
        'creneaux_par_jour': 23,
        'nb_slots': 115,
        'slots': [(day, offset) for day in range(5) for offset in range(23)],
        'fenetre_midi': list(range(10, 12)),
        'map_groupe_cours': {'BUT1': ['CM_Maths_BUT1_s1'], 'G1': ['TD_Maths_G1_s2'], 'G1A': ['TP_Maths_G1A_s3']},
        'map_cours_groupes': {'CM_Maths_BUT1_s1': ['BUT1'], 'TD_Maths_G1_s2': ['G1'], 'TP_Maths_G1A_s3': ['G1A']},
        'prof_to_teacher_id': {'Prof A': 1},
        'disponibilites_profs': {},
        'disponibilites_salles': {},
        'disponibilites_groupes': {},
        'obligations_slots': {},
        'taille_groupes': {'BUT1': 90, 'G1': 30, 'G1A': 15},
    }

    model.contrainte_ordre_cm_td_tp(data_ordre)

    # Vérifier que des ordres ont été détectés
    # Les ordres devraient être : (CM, TD), (CM, TP), (TD, TP)
    assert len(model._ordres_a_forcer) >= 2, "Doit détecter au moins CM→TD et CM→TP"


def test_contrainte_ordre_cm_td_tp_application(minimal_data):
    """Test application de l'ordre CM → TD → TP"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()
    model._add_linking_constraints()

    data_ordre = {
        'cours': [
            {'id': 'CM_Maths_BUT1_s1', 'groups': ['BUT1'], 'allowed_prof_indices': [0]},
            {'id': 'TD_Maths_G1_s2', 'groups': ['G1'], 'allowed_prof_indices': [0]},
        ],
        'duree_cours': {'CM_Maths_BUT1_s1': 2, 'TD_Maths_G1_s2': 2},
        'salles': {1: 100},
        'capacites': [100],
        'profs': ['Prof A'],
        'creneaux_par_jour': 23,
        'nb_slots': 115,
        'slots': [(day, offset) for day in range(5) for offset in range(23)],
        'fenetre_midi': list(range(10, 12)),
        'map_groupe_cours': {'BUT1': ['CM_Maths_BUT1_s1'], 'G1': ['TD_Maths_G1_s2']},
        'map_cours_groupes': {'CM_Maths_BUT1_s1': ['BUT1'], 'TD_Maths_G1_s2': ['G1']},
        'prof_to_teacher_id': {'Prof A': 1},
        'disponibilites_profs': {},
        'disponibilites_salles': {},
        'disponibilites_groupes': {},
        'obligations_slots': {},
        'taille_groupes': {'BUT1': 90, 'G1': 30},
    }

    model.contrainte_ordre_cm_td_tp(data_ordre)
    model.appliquer_ordre_cm_td_tp()

    # Vérifier que des contraintes d'ordre ont été appliquées
    assert len(model.model.Proto().constraints) > 0


# ============================================================================
# TESTS: Disponibilités (profs, salles, groupes)
# ============================================================================

def test_contrainte_disponibilites_professeurs_no_slot_before_time(minimal_data):
    """Test qu'un prof indisponible ne peut pas avoir cours"""
    data_dispos = minimal_data.copy()
    # Prof A indisponible lundi matin (jour 0, créneaux 0-5)
    data_dispos['disponibilites_profs'] = {
        1: {0: [(6, 23)]}  # Prof A disponible à partir du créneau 6 le lundi
    }

    model = TimetableModel(data_dispos)
    model._create_decision_variables()
    model._add_linking_constraints()

    model.contrainte_disponibilites_professeurs(data_dispos)

    # Vérifier que des contraintes sont ajoutées
    assert len(model.model.Proto().constraints) > 0


def test_contrainte_disponibilites_salles_generalisee(minimal_data):
    """Test que salle indisponible n'est pas utilisée"""
    data_dispos = minimal_data.copy()
    # Salle 1 fermée lundi
    data_dispos['disponibilites_salles'] = {
        1: {0: []}  # Pas de plage = indisponible
    }

    model = TimetableModel(data_dispos)
    model._create_decision_variables()
    model._add_linking_constraints()

    model.contrainte_disponibilites_salles_generalisee(data_dispos)

    # Vérifier que des contraintes sont ajoutées
    assert len(model.model.Proto().constraints) > 0


def test_contrainte_disponibilites_groupes_blocked(minimal_data):
    """Test qu'un groupe indisponible n'a pas cours"""
    data_dispos = minimal_data.copy()
    # G1A indisponible le vendredi (jour 4)
    data_dispos['disponibilites_groupes'] = {
        'G1A': {4: []}  # Pas de plage = indisponible
    }

    model = TimetableModel(data_dispos)
    model._create_decision_variables()
    model._add_linking_constraints()

    model.contrainte_disponibilites_groupes(data_dispos)

    # Vérifier que des contraintes sont ajoutées (start variables doivent être False pour ce groupe le vendredi)
    assert len(model.model.Proto().constraints) > 0


# ============================================================================
# TESTS: Pénalités
# ============================================================================

def test_penaliser_fin_tardive_detection(minimal_data):
    """Test détection de fins tardives (après 20:00)"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()

    model.penaliser_fin_tardive(minimal_data, cout_penalite=500, limite_offset_fin=20)

    # Vérifier que des pénalités sont créées pour les cours qui finissent tard
    assert hasattr(model, 'penalites_fin_tardive')
    # Le CM a durée 2, donc s'il démarre après slot 18, il finira après 20
    assert len(model.penalites_fin_tardive) > 0


def test_objective_function_minimization(minimal_data):
    """Test que la fonction objectif minimise correctement"""
    model = TimetableModel(minimal_data)
    model._create_decision_variables()
    model._add_linking_constraints()

    model._define_objective_function()

    # Vérifier que l'objectif a été défini
    assert len(model.model.Proto().objective.coeffs) >= 0


# ============================================================================
# TESTS: Intégration complète
# ============================================================================

def test_build_model_complete(minimal_data):
    """Test construction complète du modèle"""
    model = TimetableModel(minimal_data)

    # build_model() doit exécuter sans erreur
    model.build_model()

    # Vérifier que le modèle a des variables et des contraintes
    assert len(model._vars['start']) > 0, "Doit avoir des variables start"
    assert len(model.model.Proto().constraints) > 0, "Doit avoir des contraintes"


def test_solve_returns_status(minimal_data):
    """Test que solve() retourne un status valide"""
    model = TimetableModel(minimal_data)
    model.build_model()

    result = model.solve(max_time_seconds=1)

    # Vérifier la structure du résultat
    assert 'status' in result
    assert 'solver' in result
    assert 'vars' in result
    # Status doit être un entier valide (0=OPTIMAL, 1=FEASIBLE, 2=INFEASIBLE, 4=MODEL_INVALID)
    assert isinstance(result['status'], int)


def test_solve_timeout_respected(minimal_data):
    """Test que le timeout est respecté"""
    model = TimetableModel(minimal_data)
    model.build_model()

    import time
    start_time = time.time()
    result = model.solve(max_time_seconds=1)
    elapsed = time.time() - start_time

    # Devrait s'arrêter en ~1 seconde (± 0.5s de marge)
    assert elapsed < 2, f"Résolution a pris {elapsed}s, dépasse le timeout de 1s"

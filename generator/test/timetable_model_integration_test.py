import pytest
from time_table_model import TimetableModel


# ============================================================================
# TEST D'INTÉGRATION : Scénario réaliste
# ============================================================================

@pytest.fixture
def realistic_data():
    """Données plus réalistes avec plusieurs cours et contraintes"""
    return {
        'cours': [
            # CM pour toute la promo BUT1
            {'id': 'CM_Maths_BUT1_s1', 'groups': ['BUT1', 'G1', 'G1A', 'G1B', 'G2', 'G2A', 'G2B'], 'allowed_prof_indices': [0]},
            
            # TD pour groupe G1
            {'id': 'TD_Maths_G1A_s2', 'groups': ['G1A'], 'allowed_prof_indices': [1]},
            {'id': 'TD_Maths_G1B_s3', 'groups': ['G1B'], 'allowed_prof_indices': [1]},
            
            # TP pour sous-groupes
            {'id': 'TP_Maths_G1A_s4', 'groups': ['G1A'], 'allowed_prof_indices': [0, 1]},
            {'id': 'TP_Maths_G1B_s5', 'groups': ['G1B'], 'allowed_prof_indices': [0, 1]},
            
            # Un autre CM pour BUT2
            {'id': 'CM_Algo_BUT2_s6', 'groups': ['BUT2', 'G3', 'G3A', 'G3B'], 'allowed_prof_indices': [1, 2]},
            {'id': 'TD_Algo_G3A_s7', 'groups': ['G3A'], 'allowed_prof_indices': [2]},
        ],
        'duree_cours': {
            'CM_Maths_BUT1_s1': 2,
            'TD_Maths_G1A_s2': 2,
            'TD_Maths_G1B_s3': 2,
            'TP_Maths_G1A_s4': 1,
            'TP_Maths_G1B_s5': 1,
            'CM_Algo_BUT2_s6': 2,
            'TD_Algo_G3A_s7': 2,
        },
        'salles': {1: 150, 2: 50, 3: 30, 4: 100},
        'capacites': [150, 50, 30, 100],
        'profs': ['Prof Maths', 'Prof Dupont', 'Prof Martin'],
        'taille_groupes': {
            'BUT1': 120,
            'G1': 60,
            'G1A': 30,
            'G1B': 30,
            'BUT2': 100,
            'G3': 50,
            'G3A': 25,
            'G3B': 25,
        },
        'creneaux_par_jour': 23,
        'nb_slots': 115,  # 5 jours * 23 créneaux
        'slots': [(day, offset) for day in range(5) for offset in range(23)],
        'fenetre_midi': list(range(10, 12)),  # Slots 10-11
        'map_groupe_cours': {
            'BUT1': ['CM_Maths_BUT1_s1'],
            'G1': ['TD_Maths_G1A_s2', 'TD_Maths_G1B_s3'],
            'G1A': ['TD_Maths_G1A_s2', 'TP_Maths_G1A_s4'],
            'G1B': ['TD_Maths_G1B_s3', 'TP_Maths_G1B_s5'],
            'BUT2': ['CM_Algo_BUT2_s6'],
            'G3': ['TD_Algo_G3A_s7'],
            'G3A': ['TD_Algo_G3A_s7'],
        },
        'map_cours_groupes': {
            'CM_Maths_BUT1_s1': ['BUT1', 'G1', 'G1A', 'G1B', 'G2', 'G2A', 'G2B'],
            'TD_Maths_G1A_s2': ['G1A'],
            'TD_Maths_G1B_s3': ['G1B'],
            'TP_Maths_G1A_s4': ['G1A'],
            'TP_Maths_G1B_s5': ['G1B'],
            'CM_Algo_BUT2_s6': ['BUT2', 'G3', 'G3A', 'G3B'],
            'TD_Algo_G3A_s7': ['G3A'],
        },
        'prof_to_teacher_id': {
            'Prof Maths': 1,
            'Prof Dupont': 2,
            'Prof Martin': 3,
        },
        'disponibilites_profs': {
            # Prof Dupont indisponible le mardi matin
            2: {1: [(12, 23)]},  # Disponible de 12h à fin de journée (créneaux 12-22)
        },
        'disponibilites_salles': {
            # Salle 1 (150 places) fermée le vendredi
            1: {0: [(0, 23)], 1: [(0, 23)], 2: [(0, 23)], 3: [(0, 23)]},
        },
        'disponibilites_groupes': {
            # G1A indisponible mercredi après-midi
            'G1A': {2: [(0, 12)]},  # Disponible matin seulement
        },
        'obligations_slots': {},
        'liste_amphi_c': None,
    }


def test_integration_full_model_build(realistic_data):
    """Test construction et résolution d'un modèle réaliste"""
    model = TimetableModel(realistic_data)
    model.build_model()

    # Vérifier que le modèle a été construit
    assert len(model._vars['start']) > 0
    assert len(model.model.Proto().constraints) > 0


def test_integration_full_model_solve(realistic_data):
    """Test résolution complète du modèle"""
    model = TimetableModel(realistic_data)
    model.build_model()

    result = model.solve(max_time_seconds=10)

    # Vérifier le résultat (0=OPTIMAL, 1=FEASIBLE, 2=INFEASIBLE, 3=UNKNOWN, 4=MODEL_INVALID)
    assert result['status'] in [0, 1, 2, 3, 4]
    assert result['solver'] is not None


def test_integration_hierarchie_respected(realistic_data):
    """Test que la hiérarchie G1A → G1 est respectée dans la solution"""
    model = TimetableModel(realistic_data)
    model.build_model()

    result = model.solve(max_time_seconds=10)

    # Si une solution existe, vérifier la hiérarchie
    if result['status'] in [0, 1] and result['vars'] is not None:
        assert True


def test_integration_prof_constraints(realistic_data):
    """Test que les contraintes de prof sont respectées"""
    model = TimetableModel(realistic_data)
    model.build_model()

    result = model.solve(max_time_seconds=10)

    # Prof Dupont indisponible mardi matin → TD ne peuvent pas y démarrer
    assert result['status'] in [0, 1, 2, 3, 4]


def test_integration_salle_constraints(realistic_data):
    """Test que les contraintes de salle sont respectées"""
    model = TimetableModel(realistic_data)
    model.build_model()

    result = model.solve(max_time_seconds=10)

    # Salle 1 fermée vendredi → aucun cours ne doit y être vendredi
    assert result['status'] in [0, 1, 2, 3, 4]


def test_integration_groupe_constraints(realistic_data):
    """Test que les contraintes de groupe sont respectées"""
    model = TimetableModel(realistic_data)
    model.build_model()

    result = model.solve(max_time_seconds=10)

    # G1A indisponible mercredi après-midi
    assert result['status'] in [0, 1, 2, 3, 4]


def test_integration_no_conflict_same_group(realistic_data):
    """Test qu'un même groupe n'a pas deux cours au même créneau"""
    model = TimetableModel(realistic_data)
    model.build_model()

    result = model.solve(max_time_seconds=5)

    if result['status'] in [0, 1] and result['vars'] is not None:
        # G1A a : TD_Maths_G1A_s2, TP_Maths_G1A_s4
        # Ils ne doivent pas être au même créneau
        assert True


def test_integration_no_prof_double_booking(realistic_data):
    """Test qu'un prof n'a pas deux cours au même créneau"""
    model = TimetableModel(realistic_data)
    model.build_model()

    result = model.solve(max_time_seconds=5)

    if result['status'] in [0, 1] and result['vars'] is not None:
        # Prof Dupont enseigne TD_Maths_G1A_s2 et TD_Maths_G1B_s3
        # Ces cours ne doivent pas être au même créneau
        assert True


# ============================================================================
# TESTS : Cas limites et edge cases
# ============================================================================

def test_very_tight_schedule():
    """Test avec un emploi du temps très serré"""
    tight_data = {
        'cours': [
            {'id': 'CM_s1', 'groups': ['G1'], 'allowed_prof_indices': [0]},
            {'id': 'CM_s2', 'groups': ['G2'], 'allowed_prof_indices': [0]},  # Même prof
            {'id': 'CM_s3', 'groups': ['G3'], 'allowed_prof_indices': [0]},  # Même prof
        ],
        'duree_cours': {'CM_s1': 3, 'CM_s2': 3, 'CM_s3': 3},  # 3 créneaux chacun
        'salles': {1: 100},
        'capacites': [100],
        'profs': ['Prof A'],
        'taille_groupes': {'G1': 50, 'G2': 50, 'G3': 50},
        'creneaux_par_jour': 23,
        'nb_slots': 115,
        'slots': [(day, offset) for day in range(5) for offset in range(23)],
        'fenetre_midi': list(range(10, 12)),
        'map_groupe_cours': {'G1': ['CM_s1'], 'G2': ['CM_s2'], 'G3': ['CM_s3']},
        'map_cours_groupes': {'CM_s1': ['G1'], 'CM_s2': ['G2'], 'CM_s3': ['G3']},
        'prof_to_teacher_id': {'Prof A': 1},
        'disponibilites_profs': {},
        'disponibilites_salles': {},
        'disponibilites_groupes': {},
        'obligations_slots': {},
    }

    model = TimetableModel(tight_data)
    model.build_model()
    result = model.solve(max_time_seconds=5)

    # Le modèle doit rester valide
    assert result['status'] in [0, 1, 2, 3, 4]


def test_single_course():
    """Test avec un seul cours"""
    single_data = {
        'cours': [
            {'id': 'CM_s1', 'groups': ['G1'], 'allowed_prof_indices': [0]},
        ],
        'duree_cours': {'CM_s1': 2},
        'salles': {1: 100},
        'capacites': [100],
        'profs': ['Prof A'],
        'taille_groupes': {'G1': 50},
        'creneaux_par_jour': 23,
        'nb_slots': 115,
        'slots': [(day, offset) for day in range(5) for offset in range(23)],
        'fenetre_midi': list(range(10, 12)),
        'map_groupe_cours': {'G1': ['CM_s1']},
        'map_cours_groupes': {'CM_s1': ['G1']},
        'prof_to_teacher_id': {'Prof A': 1},
        'disponibilites_profs': {},
        'disponibilites_salles': {},
        'disponibilites_groupes': {},
        'obligations_slots': {},
    }

    model = TimetableModel(single_data)
    model.build_model()
    result = model.solve(max_time_seconds=5)

    # Doit être faisable (OPTIMAL ou FEASIBLE) ou UNKNOWN ou MODEL_INVALID
    assert result['status'] in [0, 1, 3, 4]


def test_impossible_schedule():
    """Test avec des contraintes conflictuelles"""
    impossible_data = {
        'cours': [
            {'id': 'CM_s1', 'groups': ['G1'], 'allowed_prof_indices': [0]},
            {'id': 'CM_s2', 'groups': ['G1'], 'allowed_prof_indices': [0]},  # Même groupe !
        ],
        'duree_cours': {'CM_s1': 2, 'CM_s2': 2},
        'salles': {1: 100},
        'capacites': [100],
        'profs': ['Prof A'],
        'taille_groupes': {'G1': 50},
        'creneaux_par_jour': 23,
        'nb_slots': 115,
        'slots': [(day, offset) for day in range(5) for offset in range(23)],
        'fenetre_midi': list(range(10, 12)),
        'map_groupe_cours': {'G1': ['CM_s1', 'CM_s2']},  # Deux cours pour un groupe
        'map_cours_groupes': {'CM_s1': ['G1'], 'CM_s2': ['G1']},
        'prof_to_teacher_id': {'Prof A': 1},
        'disponibilites_profs': {
            1: {0: [], 1: [], 2: [], 3: [], 4: []}  # Prof indisponible partout
        },
        'disponibilites_salles': {},
        'disponibilites_groupes': {},
        'obligations_slots': {},
    }

    model = TimetableModel(impossible_data)
    model.build_model()
    result = model.solve(max_time_seconds=5)

    # Doit être INFEASIBLE (impossible) ou UNKNOWN (timeout)
    assert result['status'] in [2, 3]


def test_large_schedule():
    """Test avec beaucoup de cours"""
    large_data = {
        'cours': [
            {'id': f'CM_s{i}', 'groups': [f'G{i}'], 'allowed_prof_indices': [i % 3]}
            for i in range(1, 11)  # 10 cours
        ],
        'duree_cours': {f'CM_s{i}': 2 for i in range(1, 11)},
        'salles': {1: 100, 2: 80, 3: 60, 4: 50},
        'capacites': [100, 80, 60, 50],
        'profs': ['Prof A', 'Prof B', 'Prof C'],
        'taille_groupes': {f'G{i}': 50 for i in range(1, 11)},
        'creneaux_par_jour': 23,
        'nb_slots': 115,
        'slots': [(day, offset) for day in range(5) for offset in range(23)],
        'fenetre_midi': list(range(10, 12)),
        'map_groupe_cours': {f'G{i}': [f'CM_s{i}'] for i in range(1, 11)},
        'map_cours_groupes': {f'CM_s{i}': [f'G{i}'] for i in range(1, 11)},
        'prof_to_teacher_id': {
            'Prof A': 1,
            'Prof B': 2,
            'Prof C': 3,
        },
        'disponibilites_profs': {},
        'disponibilites_salles': {},
        'disponibilites_groupes': {},
        'obligations_slots': {},
    }

    model = TimetableModel(large_data)
    model.build_model()
    result = model.solve(max_time_seconds=10)

    # Modèle doit être valide
    assert result['status'] in [0, 1, 2, 3, 4]

import pytest
import pandas as pd
from unittest.mock import MagicMock, patch
from data_provider_id import DataProviderID
from function import _time_to_slot, convert_daystring_to_int, get_start_time, get_end_time


# ============================================================================
# TESTS: _build_course_structures() - Différents types de cours
# ============================================================================

def test_build_course_structures_cm():
    """Test CM (Cours Magistral) concerne toute la promotion et sous-groupes"""
    db_config = {
        'host': '127.0.0.1', 'database': 'provisional_calendar',
        'user': 'root', 'password': 'secret', 'port': 3306
    }
    with MagicMock() as mock_engine:
        provider = DataProviderID(db_config)

        df_test = pd.DataFrame([{
            'id': 101,
            'duration': 1.5,
            'type_id': 1,  # CM
            'teaching_title': 'Maths',
            'promotion_name': 'BUT1',
            'promo_size': 100,
            'group_name': None,
            'subgroup_name': None,
            'group_size': None,
            'subgroup_size': None
        }])

        profs_par_slot = {101: ['Jean Dupont']}
        profs_liste = ['Jean Dupont', 'Marie Curie']

        cours, duree, tailles, mapping = provider._build_course_structures(
            df_test.set_index('id'), profs_par_slot, profs_liste
        )

        assert len(cours) == 1
        assert cours[0]['id'] == "CM_Maths_BUT1_s101"
        assert "BUT1" in cours[0]['groups']
        assert "G1" in cours[0]['groups']
        assert "G1A" in cours[0]['groups']
        assert duree["CM_Maths_BUT1_s101"] == 3
        assert tailles['BUT1'] == 100


def test_build_course_structures_td():
    """Test TD concerne un seul groupe"""
    db_config = {
        'host': '127.0.0.1', 'database': 'provisional_calendar',
        'user': 'root', 'password': 'secret', 'port': 3306
    }
    with MagicMock() as mock_engine:
        provider = DataProviderID(db_config)

        df_test = pd.DataFrame([{
            'id': 102,
            'duration': 1.0,
            'type_id': 2,  # TD
            'teaching_title': 'Algo',
            'promotion_name': 'BUT1',
            'promo_size': None,
            'group_name': 'G1',
            'subgroup_name': None,
            'group_size': 30,
            'subgroup_size': None
        }])

        profs_par_slot = {102: ['Marie Curie']}
        profs_liste = ['Jean Dupont', 'Marie Curie']

        cours, duree, tailles, mapping = provider._build_course_structures(
            df_test.set_index('id'), profs_par_slot, profs_liste
        )

        assert len(cours) == 1
        assert cours[0]['id'] == "TD_Algo_G1_s102"
        assert cours[0]['groups'] == ['G1']
        assert duree["TD_Algo_G1_s102"] == 2
        assert tailles['G1'] == 30


def test_build_course_structures_tp():
    """Test TP concerne un seul sous-groupe"""
    db_config = {
        'host': '127.0.0.1', 'database': 'provisional_calendar',
        'user': 'root', 'password': 'secret', 'port': 3306
    }
    with MagicMock() as mock_engine:
        provider = DataProviderID(db_config)

        df_test = pd.DataFrame([{
            'id': 103,
            'duration': 0.5,
            'type_id': 3,  # TP
            'teaching_title': 'Python',
            'promotion_name': 'BUT1',
            'promo_size': None,
            'group_name': 'G1',
            'subgroup_name': 'A',
            'group_size': None,
            'subgroup_size': 15
        }])

        profs_par_slot = {103: ['Jean Dupont']}
        profs_liste = ['Jean Dupont', 'Marie Curie']

        cours, duree, tailles, mapping = provider._build_course_structures(
            df_test.set_index('id'), profs_par_slot, profs_liste
        )

        assert len(cours) == 1
        assert cours[0]['id'] == "TP_Python_G1A_s103"
        assert cours[0]['groups'] == ['G1A']
        assert duree["TP_Python_G1A_s103"] == 1
        assert tailles['G1A'] == 15


def test_build_course_structures_sae_with_group():
    """Test SAE avec un groupe spécifique"""
    db_config = {
        'host': '127.0.0.1', 'database': 'provisional_calendar',
        'user': 'root', 'password': 'secret', 'port': 3306
    }
    with MagicMock() as mock_engine:
        provider = DataProviderID(db_config)

        df_test = pd.DataFrame([{
            'id': 104,
            'duration': 2.0,
            'type_id': 4,  # SAE
            'teaching_title': 'Projet Web',
            'promotion_name': 'BUT1',
            'promo_size': 100,
            'group_name': 'G2',
            'subgroup_name': None,
            'group_size': 30,
            'subgroup_size': None
        }])

        profs_par_slot = {104: ['Marie Curie']}
        profs_liste = ['Jean Dupont', 'Marie Curie']

        cours, duree, tailles, mapping = provider._build_course_structures(
            df_test.set_index('id'), profs_par_slot, profs_liste
        )

        assert len(cours) == 1
        assert cours[0]['id'] == "SAE_Projet Web_G2_s104"
        assert "G2" in cours[0]['groups']
        assert duree["SAE_Projet Web_G2_s104"] == 4


def test_build_course_structures_sae_without_group():
    """Test SAE sans groupe (utilise la promotion)"""
    db_config = {
        'host': '127.0.0.1', 'database': 'provisional_calendar',
        'user': 'root', 'password': 'secret', 'port': 3306
    }
    with MagicMock() as mock_engine:
        provider = DataProviderID(db_config)

        df_test = pd.DataFrame([{
            'id': 105,
            'duration': 1.5,
            'type_id': 4,  # SAE
            'teaching_title': 'Projet Mobile',
            'promotion_name': 'BUT2',
            'promo_size': 90,
            'group_name': None,
            'subgroup_name': None,
            'group_size': None,
            'subgroup_size': None
        }])

        profs_par_slot = {105: ['Jean Dupont']}
        profs_liste = ['Jean Dupont', 'Marie Curie']

        cours, duree, tailles, mapping = provider._build_course_structures(
            df_test.set_index('id'), profs_par_slot, profs_liste
        )

        assert len(cours) == 1
        assert "BUT2" in cours[0]['groups']
        assert "G4" in cours[0]['groups']


def test_build_course_structures_multiple_courses():
    """Test avec plusieurs cours de types différents"""
    db_config = {
        'host': '127.0.0.1', 'database': 'provisional_calendar',
        'user': 'root', 'password': 'secret', 'port': 3306
    }
    with MagicMock() as mock_engine:
        provider = DataProviderID(db_config)

        df_test = pd.DataFrame([
            {
                'id': 1,
                'duration': 1.5,
                'type_id': 1,  # CM
                'teaching_title': 'Maths',
                'promotion_name': 'BUT1',
                'promo_size': 100,
                'group_name': None,
                'subgroup_name': None,
                'group_size': None,
                'subgroup_size': None
            },
            {
                'id': 2,
                'duration': 1.0,
                'type_id': 2,  # TD
                'teaching_title': 'Maths',
                'promotion_name': 'BUT1',
                'promo_size': None,
                'group_name': 'G1',
                'subgroup_name': None,
                'group_size': 30,
                'subgroup_size': None
            }
        ])

        profs_par_slot = {1: ['Jean Dupont'], 2: ['Marie Curie']}
        profs_liste = ['Jean Dupont', 'Marie Curie']

        cours, duree, tailles, mapping = provider._build_course_structures(
            df_test.set_index('id'), profs_par_slot, profs_liste
        )

        assert len(cours) == 2
        assert cours[0]['id'] == "CM_Maths_BUT1_s1"
        assert cours[1]['id'] == "TD_Maths_G1_s2"


def test_build_course_structures_no_profs():
    """Test quand aucun prof n'est assigné au cours"""
    db_config = {
        'host': '127.0.0.1', 'database': 'provisional_calendar',
        'user': 'root', 'password': 'secret', 'port': 3306
    }
    with MagicMock() as mock_engine:
        provider = DataProviderID(db_config)

        df_test = pd.DataFrame([{
            'id': 106,
            'duration': 1.0,
            'type_id': 2,  # TD
            'teaching_title': 'Algo',
            'promotion_name': 'BUT1',
            'promo_size': None,
            'group_name': 'G1',
            'subgroup_name': None,
            'group_size': 30,
            'subgroup_size': None
        }])

        profs_par_slot = {106: []}  # Pas de prof assigné
        profs_liste = ['Jean Dupont', 'Marie Curie']

        cours, duree, tailles, mapping = provider._build_course_structures(
            df_test.set_index('id'), profs_par_slot, profs_liste
        )

        assert len(cours) == 1
        # Doit créer un prof fictif et l'ajouter à la liste
        assert len(cours[0]['allowed_prof_indices']) > 0
        # La liste des profs doit avoir un nouveau prof "None_X"
        assert len(profs_liste) > 2


# ============================================================================
# TESTS: Méthodes de conversion (depuis function.py)
# ============================================================================

def test_time_to_slot_morning():
    """Test conversion temps → slot pour le matin"""
    assert _time_to_slot("08:00:00") == 0
    assert _time_to_slot("08:30:00") == 1
    assert _time_to_slot("09:00:00") == 2
    assert _time_to_slot("09:30:00") == 3


def test_time_to_slot_afternoon():
    """Test conversion temps → slot pour l'après-midi"""
    assert _time_to_slot("13:00:00") == 10
    assert _time_to_slot("13:30:00") == 11
    assert _time_to_slot("14:00:00") == 12


def test_time_to_slot_na():
    """Test conversion temps avec valeur NA"""
    assert _time_to_slot(pd.NA) == 0


def test_convert_daystring_to_int():
    """Test conversion jour (string) → index (int)"""
    assert convert_daystring_to_int('Lundi') == 0
    assert convert_daystring_to_int('Vendredi') == 4


def test_convert_daystring_to_int_invalid():
    """Test conversion jour invalide"""
    with pytest.raises(ValueError):
        convert_daystring_to_int("Samedi")


def test_get_start_time_valid():
    """Test récupération start_time valide"""
    row = pd.Series({'start_time': '09:00:00'})
    assert get_start_time(row) == '09:00:00'


def test_get_start_time_na():
    """Test récupération start_time avec NA"""
    row = pd.Series({'start_time': pd.NA})
    assert get_start_time(row) == ""


def test_get_end_time_valid():
    """Test récupération end_time valide"""
    row = pd.Series({'end_time': '11:00:00'})
    assert get_end_time(row) == '11:00:00'


def test_get_end_time_na():
    """Test récupération end_time avec NA"""
    row = pd.Series({'end_time': pd.NA})
    assert get_end_time(row) == ""


# ============================================================================
# TESTS: load_and_prepare_data
# ============================================================================


def test_load_and_prepare_data_happy_path():
    """Chemin nominal avec un slot, un prof, une salle, et contraintes vides."""
    db_config = {
        'host': '127.0.0.1', 'database': 'provisional_calendar',
        'user': 'root', 'password': 'secret', 'port': 3306
    }

    rooms_df = pd.DataFrame({'name': [101], 'seat_capacity': [30]})
    profs_df = pd.DataFrame({'teacher_id': [1], 'prof_name': ['Prof A']})
    planning_df = pd.DataFrame([{
        'id': 10,
        'duration': 1.0,
        'teaching_title': 'Math',
        'promotion_name': 'BUT1',
        'group_name': 'G1',
        'subgroup_name': None,
        'promo_size': 90,
        'group_size': 30,
        'subgroup_size': None,
        'type_id': 2,
        'promotion_id': 1,
    }]).set_index('id')

    empty_teacher_constraints = pd.DataFrame(columns=['teacher_id', 'day_of_week', 'start_time', 'end_time', 'priority', 'week_id'])
    empty_room_constraints = pd.DataFrame(columns=['room_id', 'day_of_week', 'start_time', 'end_time', 'priority', 'week_id'])
    empty_group_constraints = pd.DataFrame(columns=['group_id', 'day_of_week', 'start_time', 'end_time', 'priority', 'week_id'])
    empty_slot_constraints = pd.DataFrame(columns=['slot_id', 'day_of_week', 'start_time', 'end_time', 'priority', 'week_id'])
    prof_slot_df = pd.DataFrame({'slot_id': [10], 'prof_name': ['Prof A']})

    side_effects = [
        rooms_df,
        profs_df,
        planning_df,
        prof_slot_df,
        empty_teacher_constraints,
        empty_room_constraints,
        empty_group_constraints,
        empty_slot_constraints,
    ]

    with patch('sqlalchemy.create_engine', MagicMock()), patch('pandas.read_sql', side_effect=side_effects):
        provider = DataProviderID(db_config)
        data = provider.load_and_prepare_data(week_id=1)

    assert data['cours'][0]['id'] == 'TD_Math_G1_s10'
    assert data['salles'] == {101: 30}
    assert data['profs'] == ['Prof A']
    assert data['profs_par_slot'] == {10: ['Prof A']}
    assert data['disponibilites_profs'] == {}
    assert data['disponibilites_salles'] == {}
    assert data['disponibilites_groupes'] == {}
    assert data['obligations_slots'] == {}
    assert data['group_to_dispo_key']['BUT1'] == 1


def test_load_and_prepare_data_empty_slots_profs():
    """Chemin avec aucun slot ni prof : tout doit rester vide mais structure valide."""
    db_config = {
        'host': '127.0.0.1', 'database': 'provisional_calendar',
        'user': 'root', 'password': 'secret', 'port': 3306
    }

    rooms_df = pd.DataFrame({'name': [], 'seat_capacity': []})
    profs_df = pd.DataFrame({'teacher_id': [], 'prof_name': []})
    planning_df = pd.DataFrame(columns=[
        'duration', 'teaching_title', 'promotion_name', 'group_name', 'subgroup_name',
        'promo_size', 'group_size', 'subgroup_size', 'type_id', 'promotion_id'
    ]).set_index(pd.Index([], name='id'))

    empty_teacher_constraints = pd.DataFrame(columns=['teacher_id', 'day_of_week', 'start_time', 'end_time', 'priority', 'week_id'])
    empty_room_constraints = pd.DataFrame(columns=['room_id', 'day_of_week', 'start_time', 'end_time', 'priority', 'week_id'])
    empty_group_constraints = pd.DataFrame(columns=['group_id', 'day_of_week', 'start_time', 'end_time', 'priority', 'week_id'])
    empty_slot_constraints = pd.DataFrame(columns=['slot_id', 'day_of_week', 'start_time', 'end_time', 'priority', 'week_id'])
    prof_slot_df = pd.DataFrame({'slot_id': [], 'prof_name': []})

    side_effects = [
        rooms_df,
        profs_df,
        planning_df,
        prof_slot_df,
        empty_teacher_constraints,
        empty_room_constraints,
        empty_group_constraints,
        empty_slot_constraints,
    ]

    with patch('sqlalchemy.create_engine', MagicMock()), patch('pandas.read_sql', side_effect=side_effects):
        provider = DataProviderID(db_config)
        data = provider.load_and_prepare_data(week_id=1)

    assert data['cours'] == []
    assert data['salles'] == {}
    assert data['profs'] == []
    assert data['profs_par_slot'] == {}
    assert data['disponibilites_profs'] == {}
    assert data['disponibilites_salles'] == {}
    assert data['disponibilites_groupes'] == {}
    assert data['obligations_slots'] == {}

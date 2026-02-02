import pytest
from time_table_model import TimetableModel


# ============================================================================
# TESTS: Extraction et paramétrage de la hiérarchie des groupes
# ============================================================================

def test_default_hierarchie():
    """Test que la hiérarchie par défaut est utilisée"""
    data = {
        'cours': [],
        'duree_cours': {},
        'salles': {},
        'capacites': [],
        'profs': [],
        'creneaux_par_jour': 23,
        'nb_slots': 115,
        'slots': [],
        'fenetre_midi': [],
        'map_groupe_cours': {},
        'map_cours_groupes': {},
        'prof_to_teacher_id': {},
        'disponibilites_profs': {},
        'disponibilites_salles': {},
        'disponibilites_groupes': {},
        'obligations_slots': {},
    }
    
    model = TimetableModel(data)
    
    # Vérifier que la hiérarchie par défaut est utilisée
    assert 'G1A' in model.hierarchie_groupes
    assert model.hierarchie_groupes['G1A'] == 'G1'
    assert 'G2B' in model.hierarchie_groupes
    assert model.hierarchie_groupes['G2B'] == 'G2'


def test_custom_hierarchie():
    """Test qu'on peut passer une hiérarchie personnalisée"""
    custom_hierarchie = {
        'GroupeA1': 'GroupeA',
        'GroupeA2': 'GroupeA',
        'GroupeB1': 'GroupeB',
    }
    
    data = {
        'cours': [],
        'duree_cours': {},
        'salles': {},
        'capacites': [],
        'profs': [],
        'creneaux_par_jour': 23,
        'nb_slots': 115,
        'slots': [],
        'fenetre_midi': [],
        'map_groupe_cours': {},
        'map_cours_groupes': {},
        'prof_to_teacher_id': {},
        'disponibilites_profs': {},
        'disponibilites_salles': {},
        'disponibilites_groupes': {},
        'obligations_slots': {},
        'hierarchie_groupes': custom_hierarchie,  # Passer la hiérarchie personnalisée
    }
    
    model = TimetableModel(data)
    
    # Vérifier que la hiérarchie personnalisée est utilisée
    assert model.hierarchie_groupes == custom_hierarchie
    assert 'GroupeA1' in model.hierarchie_groupes
    assert model.hierarchie_groupes['GroupeA1'] == 'GroupeA'


def test_generer_hierarchie_depuis_noms_basic():
    """Test génération basique de hiérarchie"""
    groupes = ['G1', 'G1A', 'G1B', 'G2', 'G2A']
    hierarchie = TimetableModel.generer_hierarchie_depuis_noms(groupes)
    
    assert hierarchie['G1A'] == 'G1'
    assert hierarchie['G1B'] == 'G1'
    assert hierarchie['G2A'] == 'G2'
    assert 'G1' not in hierarchie
    assert 'G2' not in hierarchie


def test_generer_hierarchie_depuis_noms_nested():
    """Test génération avec groupes imbriqués"""
    groupes = ['G1', 'G1A', 'G1B', 'G1A1', 'G1A2']
    hierarchie = TimetableModel.generer_hierarchie_depuis_noms(groupes)
    
    # G1A et G1B sont enfants de G1
    assert hierarchie['G1A'] == 'G1'
    assert hierarchie['G1B'] == 'G1'
    
    # G1A1 et G1A2 sont enfants de G1A (car on ajoute une lettre)
    # Note: G1A1 ajoute '1' (chiffre) pas une lettre, donc ne sera pas reconnu
    # G1A2 ajoute '2' (chiffre) pas une lettre, donc ne sera pas reconnu
    # Pour tester les imbriquées, on utilise des lettres :
    groupes2 = ['G1', 'G1A', 'G1B', 'G1AA', 'G1AB']
    hierarchie2 = TimetableModel.generer_hierarchie_depuis_noms(groupes2)
    assert hierarchie2['G1A'] == 'G1'
    assert hierarchie2['G1AA'] == 'G1A'
    assert hierarchie2['G1AB'] == 'G1A'
    """Test avec groupes sans sous-groupes"""
    groupes = ['G1', 'G2', 'G3']
    hierarchie = TimetableModel.generer_hierarchie_depuis_noms(groupes)
    assert hierarchie == {}


def test_generer_hierarchie_depuis_noms_single_letter():
    """Test que seule une lettre est reconnue comme sous-groupe"""
    groupes = ['Groupe', 'GroupeA', 'GroupeAB', 'GroupeA1']
    hierarchie = TimetableModel.generer_hierarchie_depuis_noms(groupes)

    # GroupeA est enfant (une lettre ajoutée)
    assert hierarchie.get('GroupeA') == 'Groupe'
    
    # GroupeAB est enfant de GroupeA (une lettre ajoutée à GroupeA)
    assert hierarchie.get('GroupeAB') == 'GroupeA'
    
    # GroupeA1 n'est pas reconnu (contient un chiffre au lieu d'une lettre)
    assert 'GroupeA1' not in hierarchie


def test_hierarchie_with_model_build():
    """Test que la hiérarchie fonctionne avec la construction du modèle"""
    custom_hierarchie = {'SubG': 'ParentG'}
    
    data = {
        'cours': [
            {'id': 'CM_s1', 'groups': ['ParentG', 'SubG'], 'allowed_prof_indices': [0]},
        ],
        'duree_cours': {'CM_s1': 2},
        'salles': {1: 100},
        'capacites': [100],
        'profs': ['Prof A'],
        'taille_groupes': {'ParentG': 50, 'SubG': 25},
        'creneaux_par_jour': 23,
        'nb_slots': 115,
        'slots': [(day, offset) for day in range(5) for offset in range(23)],
        'fenetre_midi': list(range(10, 12)),
        'map_groupe_cours': {'ParentG': ['CM_s1'], 'SubG': ['CM_s1']},
        'map_cours_groupes': {'CM_s1': ['ParentG', 'SubG']},
        'prof_to_teacher_id': {'Prof A': 1},
        'disponibilites_profs': {},
        'disponibilites_salles': {},
        'disponibilites_groupes': {},
        'obligations_slots': {},
        'hierarchie_groupes': custom_hierarchie,
    }
    
    model = TimetableModel(data)
    model.build_model()
    
    # Vérifier que le modèle a été construit avec la hiérarchie personnalisée
    assert model.hierarchie_groupes == custom_hierarchie
    assert len(model._vars['start']) > 0

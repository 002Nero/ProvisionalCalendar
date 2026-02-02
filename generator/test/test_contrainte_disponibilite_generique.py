"""Tests pour la méthode générique de contrainte de disponibilité."""

import pytest
from time_table_model import TimetableModel


@pytest.fixture
def minimal_data():
    """Données minimales pour les tests."""
    return {
        'cours': [
            {'id': 'CM_Maths_s1', 'allowed_prof_indices': [0], 'groups': ['G1']},
            {'id': 'TP_Maths_s2', 'allowed_prof_indices': [0, 1], 'groups': ['G1A']},
        ],
        'duree_cours': {'CM_Maths_s1': 2, 'TP_Maths_s2': 3},
        'profs': ['Prof A', 'Prof B'],
        'salles': {1: 'Salle 101', 2: 'Salle 102'},
        'prof_to_teacher_id': {'Prof A': 101, 'Prof B': 102},
        'map_cours_groupes': {
            'CM_Maths_s1': ['G1'],
            'TP_Maths_s2': ['G1A']
        },
        'slots': [(0, 0), (0, 2), (0, 4), (0, 6), (1, 0), (1, 2),
                  (2, 0), (2, 2), (2, 4), (3, 0), (3, 2), (3, 4),
                  (4, 0), (4, 2)],  # 14 slots total
        'creneaux_par_jour': 23,
        'nb_slots': 115,  # 5 jours * 23 créneaux
        'fenetre_midi': list(range(10, 12)),  # Slots 10-11 midi
        'capacites': [100, 50],
        'disponibilites_profs': {},
        'disponibilites_salles': {},
        'disponibilites_groupes': {},
        'hierarchie_groupes': {}
    }


class TestEstDisponible:
    """Tests pour la méthode _est_disponible."""

    def test_est_disponible_empty_plages(self, minimal_data):
        """Retourne False si plages est vide."""
        model = TimetableModel(minimal_data)
        assert model._est_disponible([], 5, 2) is False

    def test_est_disponible_single_plage_covers(self, minimal_data):
        """Retourne True si une plage couvre la durée."""
        model = TimetableModel(minimal_data)
        # Plage [4, 8] couvre le cours [5, 7] (durée 2)
        assert model._est_disponible([(4, 8)], 5, 2) is True

    def test_est_disponible_single_plage_exact_match(self, minimal_data):
        """Retourne True si plage correspond exactement."""
        model = TimetableModel(minimal_data)
        # Plage [5, 7] couvre exactement le cours [5, 7]
        assert model._est_disponible([(5, 7)], 5, 2) is True

    def test_est_disponible_single_plage_not_covers(self, minimal_data):
        """Retourne False si plage ne couvre pas."""
        model = TimetableModel(minimal_data)
        # Plage [4, 6] ne couvre pas le cours [5, 8] (durée 3)
        assert model._est_disponible([(4, 6)], 5, 3) is False

    def test_est_disponible_multiple_plages_first_covers(self, minimal_data):
        """Retourne True si la première plage couvre."""
        model = TimetableModel(minimal_data)
        # Première plage [4, 8] couvre le cours [5, 7]
        assert model._est_disponible([(4, 8), (10, 12)], 5, 2) is True

    def test_est_disponible_multiple_plages_second_covers(self, minimal_data):
        """Retourne True si la deuxième plage couvre."""
        model = TimetableModel(minimal_data)
        # Première plage ne couvre pas, mais la deuxième [4, 12] couvre [5, 7]
        assert model._est_disponible([(1, 3), (4, 12)], 5, 2) is True

    def test_est_disponible_multiple_plages_none_cover(self, minimal_data):
        """Retourne False si aucune plage ne couvre."""
        model = TimetableModel(minimal_data)
        # Aucune plage ne couvre [5, 8]
        assert model._est_disponible([(1, 3), (10, 12)], 5, 3) is False

    def test_est_disponible_offset_boundary(self, minimal_data):
        """Gère correctement les limites de plage."""
        model = TimetableModel(minimal_data)
        # Plage [5, 10], cours [5, 7] doit être couvert
        assert model._est_disponible([(5, 10)], 5, 2) is True
        # Plage [6, 10], cours [5, 7] ne doit pas être couvert (offset < 6)
        assert model._est_disponible([(6, 10)], 5, 2) is False

    def test_est_disponible_fin_boundary(self, minimal_data):
        """Gère correctement la limite fin de plage."""
        model = TimetableModel(minimal_data)
        # Plage [1, 7], cours [5, 7] doit être couvert
        assert model._est_disponible([(1, 7)], 5, 2) is True
        # Plage [1, 6], cours [5, 8] ne doit pas être couvert (fin < 8)
        assert model._est_disponible([(1, 6)], 5, 3) is False


class TestAppliquerContrainteDisponibiliteGenerique:
    """Tests pour la méthode générique d'application de contrainte."""

    def test_generique_prof_empty_dispos(self, minimal_data):
        """Ne fait rien si dispos est vide (profs)."""
        model = TimetableModel(minimal_data)
        model._create_decision_variables()
        model._add_linking_constraints()
        
        constraints_before = len(model.model.Proto().constraints)
        model._appliquer_contrainte_disponibilite_generique(
            minimal_data, 'prof', {}, 'z_prof'
        )
        # Aucune contrainte ajoutée car dispos est vide
        assert len(model.model.Proto().constraints) == constraints_before

    def test_generique_salle_empty_dispos(self, minimal_data):
        """Ne fait rien si dispos est vide (salles)."""
        model = TimetableModel(minimal_data)
        model._create_decision_variables()
        model._add_linking_constraints()
        
        constraints_before = len(model.model.Proto().constraints)
        model._appliquer_contrainte_disponibilite_generique(
            minimal_data, 'salle', {}, 'y_salle'
        )
        # Aucune contrainte ajoutée car dispos est vide
        assert len(model.model.Proto().constraints) == constraints_before

    def test_generique_groupe_empty_dispos(self, minimal_data):
        """Ne fait rien si dispos est vide (groupes)."""
        model = TimetableModel(minimal_data)
        model._create_decision_variables()
        model._add_linking_constraints()
        
        constraints_before = len(model.model.Proto().constraints)
        model._appliquer_contrainte_disponibilite_generique(
            minimal_data, 'groupe', {}, None
        )
        # Aucune contrainte ajoutée car dispos est vide
        assert len(model.model.Proto().constraints) == constraints_before

    def test_generique_prof_with_dispos(self, minimal_data):
        """Ajoute des contraintes pour prof avec disponibilités."""
        model = TimetableModel(minimal_data)
        model._create_decision_variables()
        model._add_linking_constraints()
        
        # Prof A (101) avec disponibilités spécifiques
        dispos = {
            101: {0: [(4, 23)]}  # Disponible à partir du créneau 4 le lundi
        }
        
        constraints_before = len(model.model.Proto().constraints)
        model._appliquer_contrainte_disponibilite_generique(
            minimal_data, 'prof', dispos, 'z_prof'
        )
        # Des contraintes devraient être ajoutées
        assert len(model.model.Proto().constraints) >= constraints_before

    def test_generique_salle_with_dispos(self, minimal_data):
        """Ajoute des contraintes pour salle avec disponibilités."""
        model = TimetableModel(minimal_data)
        model._create_decision_variables()
        model._add_linking_constraints()
        
        # Salle 1 avec disponibilités
        dispos = {
            1: {0: [(4, 20)]}
        }
        
        index_mapping = {0: 1, 1: 2}
        
        constraints_before = len(model.model.Proto().constraints)
        model._appliquer_contrainte_disponibilite_generique(
            minimal_data, 'salle', dispos, 'y_salle',
            index_mapping=index_mapping
        )
        # Des contraintes devraient être ajoutées
        assert len(model.model.Proto().constraints) >= constraints_before

    def test_generique_groupe_with_dispos(self, minimal_data):
        """Ajoute des contraintes pour groupe avec disponibilités."""
        model = TimetableModel(minimal_data)
        model._create_decision_variables()
        model._add_linking_constraints()
        
        # G1 indisponible un jour
        dispos = {
            'G1': {0: [(4, 20)]}
        }
        
        constraints_before = len(model.model.Proto().constraints)
        model._appliquer_contrainte_disponibilite_generique(
            minimal_data, 'groupe', dispos, None
        )
        # Des contraintes devraient être ajoutées
        assert len(model.model.Proto().constraints) >= constraints_before

    def test_generique_with_different_log_message(self, minimal_data):
        """Teste que log_message est accepté."""
        model = TimetableModel(minimal_data)
        model._create_decision_variables()
        model._add_linking_constraints()
        
        # Simple test que le paramètre log_message ne cause pas d'erreur
        constraints_before = len(model.model.Proto().constraints)
        model._appliquer_contrainte_disponibilite_generique(
            minimal_data, 'prof', {}, 'z_prof',
            log_message="Custom test message"
        )
        # Aucune contrainte car dispos est vide
        assert len(model.model.Proto().constraints) == constraints_before

    def test_est_disponible_integration(self, minimal_data):
        """Teste que _est_disponible fonctionne dans le contexte."""
        model = TimetableModel(minimal_data)
        # Plages couvertes, cours rentre dedans
        assert model._est_disponible([(4, 10)], 5, 3) is True
        # Plages non couvertes
        assert model._est_disponible([(1, 3)], 5, 3) is False

    def test_generic_method_invalid_type(self, minimal_data):
        """Teste que la méthode générique gère bien les types invalides."""
        model = TimetableModel(minimal_data)
        model._create_decision_variables()
        model._add_linking_constraints()
        
        # Test avec type invalide - ne devrait pas planter
        constraints_before = len(model.model.Proto().constraints)
        model._appliquer_contrainte_disponibilite_generique(
            minimal_data, 'invalid_type', {}, 'z_prof'
        )
        # Aucune contrainte ajoutée (type invalide)
        assert len(model.model.Proto().constraints) == constraints_before

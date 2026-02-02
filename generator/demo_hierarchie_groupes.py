#!/usr/bin/env python3
"""
Démonstration de l'extraction de la hiérarchie des groupes dans TimetableModel
"""

from time_table_model import TimetableModel

print("=" * 80)
print("DÉMONSTRATION: Extraction de la hiérarchie des groupes")
print("=" * 80)

# ============================================================================
# 1. Hiérarchie par défaut
# ============================================================================
print("\n1. HIÉRARCHIE PAR DÉFAUT")
print("-" * 80)

data_default = {
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

model_default = TimetableModel(data_default)
print("Hiérarchie par défaut:")
for sous_groupe, parent in sorted(model_default.hierarchie_groupes.items()):
    print(f"  {sous_groupe} → {parent}")

# ============================================================================
# 2. Hiérarchie personnalisée
# ============================================================================
print("\n2. HIÉRARCHIE PERSONNALISÉE")
print("-" * 80)

custom_hierarchie = {
    'SCI_A': 'SCI',
    'SCI_B': 'SCI',
    'INFO_1': 'INFO',
    'INFO_2': 'INFO',
    'MATH_A': 'MATH',
}

data_custom = data_default.copy()
data_custom['hierarchie_groupes'] = custom_hierarchie

model_custom = TimetableModel(data_custom)
print("Hiérarchie personnalisée:")
for sous_groupe, parent in sorted(model_custom.hierarchie_groupes.items()):
    print(f"  {sous_groupe} → {parent}")

# ============================================================================
# 3. Génération automatique depuis les noms
# ============================================================================
print("\n3. GÉNÉRATION AUTOMATIQUE DEPUIS LES NOMS")
print("-" * 80)

groupes = ['G1', 'G1A', 'G1B', 'G2', 'G2A', 'G2B', 'G3', 'G3A']
hierarchie_auto = TimetableModel.generer_hierarchie_depuis_noms(groupes)

print(f"Groupes: {groupes}")
print("\nHiérarchie générée automatiquement:")
for sous_groupe, parent in sorted(hierarchie_auto.items()):
    print(f"  {sous_groupe} → {parent}")


print("\n" + "=" * 80)

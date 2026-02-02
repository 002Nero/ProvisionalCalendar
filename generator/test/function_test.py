import pandas as pd
import pytest

from function import (
    get_start_time,
    get_end_time,
    convert_daystring_to_int,
    convert_days_int_to_string,
    _time_to_slot,
    recup_cours,
    recup_id_slot_from_str_to_int,
    recuperation_indisponibilites,
    recuperation_indisponibilites_rooms,
    recuperation_indisponibilites_slot,
    get_availabilityRoom_From_Unavailable,
)


def test_start_end_time_values_and_na():
    row = pd.Series({'start_time': '09:30:00', 'end_time': '11:00:00'})
    assert get_start_time(row) == '09:30:00'
    assert get_end_time(row) == '11:00:00'

    row_na = pd.Series({'start_time': pd.NA, 'end_time': pd.NA})
    assert get_start_time(row_na) == ''
    assert get_end_time(row_na) == ''


def test_day_conversions_round_trip():
    assert convert_daystring_to_int('Lundi') == 0
    assert convert_daystring_to_int('Vendredi') == 4
    assert convert_days_int_to_string(2) == 'Mercredi'


def test_time_to_slot_mapping():
    assert _time_to_slot('08:00:00') == 0
    assert _time_to_slot('09:30:00') == 3
    assert _time_to_slot('13:30:00') == 11
    assert _time_to_slot(pd.NA) == 0


def test_recup_cours_and_slot_id():
    cid = 'CM_Maths_BUT1_s42'
    type_cour, nom_cour = recup_cours(cid)
    assert type_cour == 'CM'
    assert nom_cour == 'Maths'
    assert recup_id_slot_from_str_to_int(cid) == 42


def test_recuperation_indisponibilites_prof():
    df = pd.DataFrame([
        {
            'teacher_id': 1,
            'day_of_week': 'lundi',
            'start_time': '09:00:00',
            'end_time': '11:00:00',
        }
    ])
    res = recuperation_indisponibilites(df, {})
    assert res == {1: {'lundi': [(2, 6)]}}


def test_recuperation_indisponibilites_rooms():
    df = pd.DataFrame([
        {
            'room_id': 10,
            'day_of_week': 'Lundi',
            'start_time': '10:00:00',
            'end_time': '11:00:00',
        }
    ])
    res = recuperation_indisponibilites_rooms(df, {})
    assert res == {10: {'Lundi': [(4, 6)]}}


def test_recuperation_indisponibilites_slot():
    df = pd.DataFrame([
        {
            'slot_id': 7,
            'day_of_week': 'Lundi',
            'start_time': '09:00:00',
            'end_time': '10:00:00',
        }
    ])
    res = recuperation_indisponibilites_slot(df, {})
    assert res == {7: {0: [(2, 4)]}}


def test_recuperation_indisponibilites_slot_realistic_row():
    """Equivalent aux données : 51000033, Lundi, 10:00-11:00, week 221, priority hard"""
    df = pd.DataFrame([
        {
            'slot_id': 51000033,
            'day_of_week': 'Lundi',
            'start_time': '10:00:00',
            'end_time': '11:00:00',
            'priority': 'hard',
            'week_id': 221,
        }
    ])

    res = recuperation_indisponibilites_slot(df, {})

    # 10h -> slot 4, 11h -> slot 6 (30 min pas, départ à 8h)
    assert res == {51000033: {0: [(4, 6)]}}


def test_get_availability_room_from_unavailable():
    df = pd.DataFrame([
        {
            'room_id': 10,
            'day_of_week': 'Lundi',
            'start_time': '10:00:00',
            'end_time': '11:00:00',
        }
    ])
    # Avec 6 créneaux (8h->11h), l'indisponibilité 10h-11h laisse 8h-9h30 dispo
    res = get_availabilityRoom_From_Unavailable(df, 6)

    # Vérifie le jour concerné (lundi index 0)
    assert res[10][0] == [(0, 3)]
    # Les autres jours reçoivent la disponibilité complète
    for day_idx in [1, 2, 3, 4]:
        assert res[10][day_idx] == [(0, 6)]

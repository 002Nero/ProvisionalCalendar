from sqlalchemy import create_engine
from typing import Dict, Any, Tuple

import pandas as pd
from sqlalchemy import create_engine

DAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi']

def get_end_time(row) -> str:
    if pd.isna(row['end_time']):
        return ""
    return str(row['end_time'])[-8:]


def get_start_time(row) -> str:
    if pd.isna(row['start_time']):
        return ""
    return str(row['start_time'])[-8:]


def convert_day_string_to_int(day: str) -> int:
    return DAYS.index(day)

def convert_days_int_to_string(day:int)->str:
    return DAYS[day]

def _time_to_slot(time_str: str) -> int:
    """'13:30:00' → 11 (8h=0, 8h30=1, ..., 13h30=11)"""
    if pd.isna(time_str):
        return 0
    h, m, _ = map(int, str(time_str).split(':'))
    return (h - 8) * 2 + (m // 30)
def get_availability_prof_from_unavailable(df_dispos,creneaux_par_jour):
    disponibilites_profs = {}
    indisponibilites_profs = {}
    indisponibilites_profs=recuperation_indisponibilites(df_dispos, indisponibilites_profs)
    disponibilites_profs=recuperation_disponibilites_profs(creneaux_par_jour, disponibilites_profs, indisponibilites_profs)
    return disponibilites_profs


def recuperation_disponibilites_profs(creneaux_par_jour, disponibilites_profs: dict[Any, Any],
                                      indisponibilites_profs: dict[Any, Any])-> dict[Any, Any]:
    for i in indisponibilites_profs:
        for day in DAYS:
            if day in indisponibilites_profs[i]:
                h_min = 0
                h_max = creneaux_par_jour
                for k in indisponibilites_profs[i][day]:
                    if k[0] == '':
                        continue
                    else:
                        if h_min < k[1] < creneaux_par_jour:
                            h_min = k[1]
                            disponibilites_profs.setdefault(i, {}).setdefault(DAYS.index(day), []).append((h_min + 1, h_max))
                            continue
                        if h_max > k[0]:
                            h_max = k[0]
                            disponibilites_profs.setdefault(i, {}).setdefault(DAYS.index(day), []).append((h_min, h_max - 1))
            else:
                disponibilites_profs.setdefault(i, {}).setdefault(DAYS.index(day), []).append((0, creneaux_par_jour))
    return disponibilites_profs


def recuperation_indisponibilites(df_dispos, indisponibilites_profs: dict[Any, Any])-> dict[Any, Any]:
    for _, row in df_dispos.iterrows():
        teacher_id = row['teacher_id']
        recup_indispo_global(indisponibilites_profs, row, teacher_id)
    return indisponibilites_profs


def recup_indispo_global(indisponibilites_global: dict[Any, Any], row, teacher_id):
    day_id = (row['day_of_week'])  # 0 = lundi, 4 = vendredi
    debut_str = get_start_time(row)
    fin_str = get_end_time(row)
    if debut_str != "":
        debut_slot = _time_to_slot(debut_str)
        fin_slot = _time_to_slot(fin_str)
    else:
        debut_slot = ""
        fin_slot = ""
    indisponibilites_global.setdefault(teacher_id, {}).setdefault(day_id, []).append((debut_slot, fin_slot))


def get_availability_room_from_unavailable(df_dispos,creneaux_par_jour):
    disponibilites_salles = {}
    indisponibilites_salles = {}
    indisponibilites_salles=recuperation_indisponibilites_rooms(df_dispos, indisponibilites_salles)

    disponibilites_salles=recuperation_disponibilites_rooms(creneaux_par_jour, disponibilites_salles, indisponibilites_salles)

    return disponibilites_salles


def recuperation_disponibilites_rooms(creneaux_par_jour, disponibilites_salles: dict[Any, Any],
                                      indisponibilites_salles: dict[Any, Any])-> dict[Any, Any]:
    for i in indisponibilites_salles:
        for day in DAYS:
            if day in indisponibilites_salles[i]:
                h_min = 0
                h_max = creneaux_par_jour
                for k in indisponibilites_salles[i][day]:
                    if k[0] == '':
                        continue
                    else:
                        if h_min < k[1] < creneaux_par_jour:
                            h_min = k[1]
                            disponibilites_salles.setdefault(i, {}).setdefault(DAYS.index(day), []).append((h_min + 1, h_max))
                            continue
                        if h_max > k[0]:
                            h_max = k[0]
                            disponibilites_salles.setdefault(i, {}).setdefault(DAYS.index(day), []).append((h_min, h_max - 1))
            else:
                disponibilites_salles.setdefault(i, {}).setdefault(DAYS.index(day), []).append((0, creneaux_par_jour))
    return disponibilites_salles
def recup_cours(cid:str):
    id_cour= cid.split("_")
    type_cour= id_cour[0]
    nom_cour= id_cour[1]

    return type_cour,nom_cour
def recup_id_slot_from_str_to_int(cid:str):
    id_cour = cid.split("_")
    id_slot = id_cour[-1]
    return int(id_slot[1:])

def recuperation_indisponibilites_rooms(df_dispos, indisponibilites_profs: dict[Any, Any])-> dict[Any, Any]:
    for _, row in df_dispos.iterrows():
        room_id = row['room_id']
        recup_indispo_global(indisponibilites_profs, row, room_id)
    return indisponibilites_profs


def get_availability_group_from_unavailable(df_dispos,creneaux_par_jour):
    disponibilites_groupes = {}
    indisponibilites_groupes = {}
    indisponibilites_groupes=recuperation_indisponibilites_group(df_dispos, indisponibilites_groupes)

    disponibilites_groupes=recuperation_disponibilites_group(creneaux_par_jour, disponibilites_groupes, indisponibilites_groupes)
    print("dispos groupes : ", disponibilites_groupes)
    return disponibilites_groupes


def recuperation_disponibilites_group(creneaux_par_jour, disponibilites_groupes: dict[Any, Any],
                                      indisponibilites_groupes: dict[Any, Any])-> dict[Any, Any]:
    for i in indisponibilites_groupes:
        for day in DAYS:
            if day in indisponibilites_groupes[i]:
                h_min = 0
                h_max = creneaux_par_jour
                for k in indisponibilites_groupes[i][day]:
                    if k[0] == '':
                        continue
                    else:
                        if h_min < k[1] < creneaux_par_jour:
                            h_min = k[1]
                            disponibilites_groupes.setdefault(i, {}).setdefault(DAYS.index(day), []).append((h_min + 1, h_max))
                            continue
                        if h_max > k[0]:
                            h_max = k[0]
                            disponibilites_groupes.setdefault(i, {}).setdefault(DAYS.index(day), []).append((h_min, h_max - 1))
            else:
                disponibilites_groupes.setdefault(i, {}).setdefault(DAYS.index(day), []).append((0, creneaux_par_jour))
    return disponibilites_groupes


def recuperation_indisponibilites_group(df_dispos, indisponibilites_groupes: dict[Any, Any])-> dict[Any, Any]:
    for _, row in df_dispos.iterrows():
        group_id = row['group_id']
        recup_indispo_global(indisponibilites_groupes, row, group_id)
    return indisponibilites_groupes

def get_availability_slot_from_unavailable(df_dispos,creneaux_par_jour):
    indisponibilites_groupes = {}
    disponibilites_slot=recuperation_indisponibilites_slot(df_dispos, indisponibilites_groupes)
    return  disponibilites_slot

def recuperation_disponibilites_slot(creneaux_par_jour, disponibilites_groupes: dict[Any, Any],
                                      indisponibilites_groupes: dict[Any, Any])-> dict[Any, Any]:
    for i in indisponibilites_groupes:
        for day in DAYS:
            if day in indisponibilites_groupes[i]:
                h_min = 0
                h_max = creneaux_par_jour
                for k in indisponibilites_groupes[i][day]:
                    if k[0] == '':
                        continue
                    else:
                        if h_min < k[1] < creneaux_par_jour:
                            h_min = k[1]
                            disponibilites_groupes.setdefault(i, {}).setdefault(DAYS.index(day), []).append((h_min + 1, h_max))
                            continue
                        if h_max > k[0]:
                            h_max = k[0]
                            disponibilites_groupes.setdefault(i, {}).setdefault(DAYS.index(day), []).append((h_min, h_max - 1))
            else:
                disponibilites_groupes.setdefault(i, {}).setdefault(DAYS.index(day), []).append((0, creneaux_par_jour))
    return disponibilites_groupes


def recuperation_indisponibilites_slot(df_dispos, indisponibilites_groupes: dict[Any, Any])-> dict[Any, Any]:
    for _, row in df_dispos.iterrows():
        slot_id = row['slot_id']
        day_id = (row['day_of_week']).lower()  # 0 = lundi, 4 = vendredi - normalize to lowercase
        debut_str = get_start_time(row)
        fin_str = get_end_time(row)
        if debut_str != "":
            debut_slot = _time_to_slot(debut_str)
            fin_slot = _time_to_slot(fin_str)
        else:
            debut_slot = ""
            fin_slot = ""
        indisponibilites_groupes.setdefault(slot_id, {}).setdefault(DAYS.index(day_id), []).append((debut_slot, fin_slot))
    return indisponibilites_groupes


class FunctionTest:
    def __init__(self, db_config: Dict[str, Any]):
        self.db_config = db_config
        self.engine = create_engine(
            f"mysql+mysqlconnector://{db_config['user']}:{db_config['password']}@"
            f"{db_config['host']}:{db_config['port']}/{db_config['database']}"
        )
    def load_and_prepare_data(self):
        week_id=221
        query_dispos = """
                       SELECT tc.teacher_id, tc.day_of_week, tc.start_time, tc.end_time, tc.priority, tc.week_id
                       FROM teacher_constraints tc
                       WHERE tc.active = 1
                         AND (tc.week_id = %(week_id)s OR tc.week_id IS NULL)
                         AND (
                           tc.week_id = %(week_id)s
                               OR (tc.week_id IS NULL
                               AND NOT EXISTS (SELECT 1 \
                                               FROM teacher_constraints tc2 \
                                               WHERE tc2.teacher_id = tc.teacher_id \
                                                 AND tc2.day_of_week = tc.day_of_week \
                                                 AND tc2.week_id = %(week_id)s \
                                                 AND tc2.active = 1)
                               )
                           ) \
                       """
        df_dispos_profs = pd.read_sql(query_dispos, self.engine, params={"week_id": week_id})

        query_dispos = """
                       SELECT rc.room_id, rc.day_of_week, rc.start_time, rc.end_time, rc.priority, rc.week_id
                       FROM room_constraints rc
                       WHERE rc.active = 1
                         AND (rc.week_id = %(week_id)s OR rc.week_id IS NULL)
                         AND (
                           rc.week_id = %(week_id)s
                               OR (rc.week_id IS NULL
                               AND NOT EXISTS (SELECT 1 \
                                               FROM room_constraints rc2 \
                                               WHERE rc2.room_id = rc.room_id \
                                                 AND rc2.day_of_week = rc.day_of_week \
                                                 AND rc2.week_id = %(week_id)s \
                                                 AND rc2.active = 1)
                               )
                           ) \
                       """
        df_dispos_salles = pd.read_sql(query_dispos, self.engine, params={"week_id": week_id})
        print("df slots : ",df_dispos_salles)

        query_dispos = """
                       SELECT gc.group_id, gc.day_of_week, gc.start_time, gc.end_time, gc.priority, gc.week_id
                       FROM group_constraints gc
                       WHERE gc.active = 1
                         AND (gc.week_id = %(week_id)s OR gc.week_id IS NULL)
                         AND (
                           gc.week_id = %(week_id)s
                               OR (gc.week_id IS NULL
                               AND NOT EXISTS (SELECT 1 \
                                               FROM group_constraints gc2 \
                                               WHERE gc2.group_id = gc.group_id \
                                                 AND gc2.day_of_week = gc.day_of_week \
                                                 AND gc2.week_id = %(week_id)s \
                                                 AND gc2.active = 1)
                               )
                           ) \
                       """
        df_dispos_groupes = pd.read_sql(query_dispos, self.engine, params={"week_id": week_id})
        print("df slots : ",df_dispos_groupes)

        query_dispos = """
                       SELECT sc.slot_id, sc.day_of_week, sc.start_time, sc.end_time, sc.priority, sc.week_id
                       FROM slot_constraints sc
                       WHERE sc.active = 1
                         AND (sc.week_id = %(week_id)s OR sc.week_id IS NULL)
                         AND (
                           sc.week_id = %(week_id)s
                               OR (sc.week_id IS NULL
                               AND NOT EXISTS (SELECT 1 \
                                               FROM slot_constraints sc2 \
                                               WHERE sc2.slot_id = sc.slot_id \
                                                 AND sc2.day_of_week = sc.day_of_week \
                                                 AND sc2.week_id = %(week_id)s \
                                                 AND sc2.active = 1)
                               )
                           ) \
                       """
        df_dispos_slots = pd.read_sql(query_dispos, self.engine, params={"week_id": week_id})
        print("df slots : ",df_dispos_slots)



if __name__ == "__main__":
    DB_CONFIG = {
        'host': '127.0.0.1', 'database': 'provisional_calendar',
        'user': 'root', 'password': 'secret', 'port': 3306
    }
    data_provider = FunctionTest(DB_CONFIG)
    data_provider.load_and_prepare_data()
    print(recup_cours("CM_R1.01 Initiation au développement_BUT1_s7000000"))
    print(recup_id_slot_from_str_to_int("développement_BUT1_s7000000"))
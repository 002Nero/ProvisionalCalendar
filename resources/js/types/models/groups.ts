import { Item } from "./utils";

export type Subgroup = Item & {
    student_amount?: number;
};

export type Group = Item & {
    subgroups?: Subgroup[];
    student_amount?: number;
};

export type Promotion = Item & {
    groups?: Group[];
    student_amount?: number;
};

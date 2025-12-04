import { Item } from "./utils";
import { Period } from "./periods";
import { Teacher } from "./teachers";

/**
 * @interface Teaching
 *
 * Représente un enseignement.
 */
export type Teaching = Item & {
    /**
     * Le code apogee de l'enseignement.
     */
    apogee_code: string;
    mcccFormInput: MCCCFormInput;
    /**
     * Les enseignants de l'enseignement.
     */
    period?: Period;
    teachers?: Teacher[];
};

export interface MCCCFormInput {
    /**
     * Le nombre initial de CM de l'enseignement.
     */
    cm_hours?: number;
    /**
     * Le nombre initial de TD de l'enseignement.
     */
    initial_td?: number;
    /**
     * Le nombre initial de TP de l'enseignement.
     */
    initial_tp?: number;
    /**
     * Le nombre continu de TD de l'enseignement.
     */
    continuing_td?: number;
    /**
     * Le nombre continu de TP de l'enseignement.
     */
    continuing_tp?: number;
}

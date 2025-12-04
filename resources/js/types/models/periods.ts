export type Period = {
    id: number;
    name: string;
};

export type ApiPeriodResponse = {
    semesters?: ApiSemester[];
    trimesters?: ApiTrimester[];
};

export enum PeriodType {
    SEMESTER = "SEMESTER",
    TRIMESTER = "TRIMESTER",
}

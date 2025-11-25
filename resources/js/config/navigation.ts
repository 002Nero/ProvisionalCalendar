import { MenuItem } from "@/types/models/utils";

const provisionnalCalendarMenuItems: MenuItem[] = [
    { iconClass: "User", label: "Groupes", route: "groupes" },
    {
        iconClass: "Book",
        label: "Enseignants/Enseignements",
        route: "enseignants-enseignements",
    },
    {
        iconClass: "Calendar",
        label: "Calendrier Prévisionnels",
        route: "editeur",
    },
    {
        iconClass: "Clock",
        label: "Emplois du temps",
        route: "edt"

    },
];

const configurationMenuItems: MenuItem[] = [
    { iconClass: "Pen", label: "Libéllés", route: "labels" },
    { iconClass: "User", label: "Utilisateurs", route: "utilisateurs" },
];

export const sidebarMenuItems: MenuItem[] = [
    {
        iconClass: "Calendar",
        label: "Calendrier Prévisionnel",
        route: "calendrier-previsionnel",
        submenu: provisionnalCalendarMenuItems,
    },
    {
        iconClass: "Clock",
        label: "EDT",
        route: "edt"
    },
    {
        iconClass: "NotebookText",
        label: "Service",
        route: "service",
        disable: true,
    },
    {
        iconClass: "Settings",
        label: "Configurations",
        route: "configurations",
        submenu: configurationMenuItems,
    },
];

import axios from "axios";
import { API_ENDPOINTS, MESSAGES } from "@/constants";
import { Teaching } from "@/types/models/teachings";

export const useTeachingService = () => {
    const getTeachings = (
        promotionId: number,
    ): Promise<Teaching[]> => {
        return new Promise((resolve, reject) =>
            axios
                .get(`${API_ENDPOINTS.TEACHINGS}/${promotionId}`)
                .then((response) => {
                    const teachings: Teaching[] = [];
                    for (const teaching of response.data) {
                        teachings.push({
                            id: teaching.id,
                            name: teaching.name,
                            apogee_code: teaching.apogee_code,
                            mcccFormInput: {
                                initial_tp: teaching.tp_hours_initial,
                                continuing_tp: teaching.tp_hours_continued,
                                initial_td: teaching.td_hours_initial,
                                continuing_td: teaching.td_hours_continued,
                                cm_hours: teaching.cm_hours,
                            },
                        });
                    }
                    resolve(teachings);
                })
                .catch((error) => {
                    if (error.response?.data?.error) {
                        reject(error.response.data.error);
                    } else {
                        reject(MESSAGES.DEFAULT_ERROR_MESSAGE);
                    }
                })
        );
    };

    const getTeaching = (teachingId: number): Promise<Teaching> => {
        return new Promise((resolve, reject) =>
            axios
                .get(`${API_ENDPOINTS.TEACHING}/${teachingId}`)
                .then((response) => resolve(response.data))
                .catch((error) => {
                    if (error.response?.data?.error) {
                        reject(error.response.data.error);
                    } else {
                        reject(MESSAGES.DEFAULT_ERROR_MESSAGE);
                    }
                })
        );
    };

    const addTeaching = (
        promotionId: number,
        teaching: Teaching
    ): Promise<Teaching> => {
        return new Promise((resolve, reject) =>
            axios
                .post(`${API_ENDPOINTS.TEACHING}/${promotionId}`, teaching)
                .then((response) => resolve(response.data))
                .catch((error) => {
                    if (error.response?.data?.error) {
                        reject(error.response.data.error);
                    } else {
                        reject(MESSAGES.DEFAULT_ERROR_MESSAGE);
                    }
                })
        );
    };

    const updateTeaching = (teaching: Teaching): Promise<Teaching> => {
        return new Promise((resolve, reject) =>
            axios
                .put(`${API_ENDPOINTS.TEACHING}/${teaching.id}`, teaching)
                .then((response) => resolve(response.data))
                .catch((error) => {
                    if (error.response?.data?.error) {
                        reject(error.response.data.error);
                    } else {
                        reject(MESSAGES.DEFAULT_ERROR_MESSAGE);
                    }
                })
        );
    };

    const deleteTeaching = (teachingId: number): Promise<Teaching> => {
        return new Promise((resolve, reject) =>
            axios
                .delete(`${API_ENDPOINTS.TEACHING}/${teachingId}`)
                .then((response) => resolve(response.data))
                .catch((error) => {
                    if (error.response?.data?.error) {
                        reject(error.response.data.error);
                    } else {
                        reject(MESSAGES.DEFAULT_ERROR_MESSAGE);
                    }
                })
        );
    };

    return {
        getTeachings,
        getTeaching,
        addTeaching,
        updateTeaching,
        deleteTeaching,
    };
};

import axios from "axios";
import { API_ENDPOINTS, MESSAGES } from "@/constants";
import { Teacher, User } from "@/types/models/teachers";
import { Teaching } from "@/types/models/teachings";

export const useTeacherService = () => {
    const getTeachers = (yearId: number): Promise<Teacher[]> => {
        return new Promise((resolve, reject) =>
            axios
                .get(`${API_ENDPOINTS.TEACHERS}/${yearId}`)
                .then((response) => {
                    const data = response.data as unknown as Array<unknown>;
                    resolve(
                        data.map((teacher) => {
                            const raw = teacher as unknown as Record<string, unknown>;
                            const id = typeof raw['id'] === 'number' ? raw['id'] as number : Number(raw['id']);
                            const firstName = typeof raw['first_name'] === 'string' ? raw['first_name'] as string : "";
                            const lastName = typeof raw['last_name'] === 'string' ? raw['last_name'] as string : "";
                            const name = `${firstName} ${lastName}`.trim();
                            return ({
                                id,
                                name,
                                // keep raw data on the object for other consumers if needed
                                // eslint-disable-next-line @typescript-eslint/ban-ts-comment
                                // @ts-ignore
                                ...raw
                            }) as unknown as Teacher;
                        })
                    );
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

    const getTeacher = (teacherId: number, users: User[]): Promise<Teacher> => {
        return new Promise((resolve, reject) =>
            axios
                .get(`${API_ENDPOINTS.TEACHER}/${teacherId}`)
                .then((response) =>
                    resolve({
                        code: response.data.acronym,
                        user: users.find((u) => u.id === response.data.user_id),
                        ...response.data,
                    })
                )
                .catch((error) => {
                    if (error.response?.data?.error) {
                        reject(error.response.data.error);
                    } else {
                        reject(MESSAGES.DEFAULT_ERROR_MESSAGE);
                    }
                })
        );
    };

    const addTeacher = (
        promotionId: number,
        teacher: Teacher
    ): Promise<Teacher> => {
        return new Promise((resolve, reject) =>
            axios
                .post(`${API_ENDPOINTS.TEACHER}/${promotionId}`, {
                    acronym: teacher.code,
                    user_id: teacher.user?.id,
                })
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

    const updateTeacher = (teacher: Teacher): Promise<Teacher> => {
        return new Promise((resolve, reject) =>
            axios
                .put(`${API_ENDPOINTS.TEACHER}/${teacher.id}`, {
                    acronym: teacher.code,
                    user_id: teacher.user?.id,
                })
                .then((response) => resolve(response.data))
                .catch((error) => {
                    console.log(error);
                    if (error.response?.data?.error) {
                        reject(error.response.data.error);
                    } else {
                        reject(MESSAGES.DEFAULT_ERROR_MESSAGE);
                    }
                })
        );
    };

    const deleteTeacher = (teacherId: number): Promise<Teacher> => {
        return new Promise((resolve, reject) =>
            axios
                .delete(`${API_ENDPOINTS.TEACHER}/${teacherId}`)
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

    const getTeachingsByTeacher = (teacherId: number): Promise<Teaching[]> => {
        return new Promise((resolve, reject) =>
            axios
                .get(`${API_ENDPOINTS.TEACHER}/teachings/${teacherId}`)
                .then((response) => {
                    const data = response.data as unknown as Array<unknown>;
                    const teachings: Teaching[] = data.map((item) => {
                        const raw = item as unknown as Record<string, unknown>;
                        const id = typeof raw['id'] === 'number' ? raw['id'] as number : Number(raw['id']);
                        const name = typeof raw['title'] === 'string' ? raw['title'] as string : (typeof raw['name'] === 'string' ? raw['name'] as string : "");
                        const apogee_code = typeof raw['apogee_code'] === 'string' ? raw['apogee_code'] as string : "";
                        return ({
                            id,
                            name,
                            apogee_code,
                        } as unknown) as Teaching;
                    });
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

    return {
        getTeachers,
        getTeacher,
        addTeacher,
        updateTeacher,
        deleteTeacher,
        getTeachingsByTeacher,
    };
};

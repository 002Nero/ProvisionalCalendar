import axios from "axios";
import { API_ENDPOINTS, MESSAGES } from "@/constants";
import { Period } from "@/types/models/periods";

export const usePeriodService = () => {
    const getPeriods = (yearId: number): Promise<Period> => {
        return new Promise((resolve, reject) =>
            axios
                .get<Period>(`${API_ENDPOINTS.PERIODS}/${yearId}`)
                .then((response) => {
                    if (response.data) {
                        resolve(
                            response.data
                        );
                    } else {
                        reject(MESSAGES.DEFAULT_ERROR_MESSAGE);
                    }
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
        getPeriods,
    };
};

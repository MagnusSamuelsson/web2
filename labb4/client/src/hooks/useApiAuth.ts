import { useQuery } from "@tanstack/react-query";
import api from "../services/apiClient";
import { AccessToken } from "../models/accessToken";


export const useApiAuth = () => {
    return useQuery<AccessToken, Error>({
        queryKey: ["authToken"],
        queryFn: async () => {
            return await api.get<AccessToken>("/api/auth/token", false);
        },
        staleTime: 1000 * 60 * 13,
        refetchInterval() {
            return 1000 * 60 * 13;
        },
        retry: false
    });
};
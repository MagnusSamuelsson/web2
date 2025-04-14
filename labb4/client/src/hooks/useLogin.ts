import { useMutation } from "@tanstack/react-query";
import { queryClient } from "../services/queryClient";
import api from "../services/apiClient";

interface LoginCredentials {
    username: string;
    password: string;
}

interface AuthResponse {
    access_token: string;
    user: {
        id: number;
        username: string;
        email: string;
    };
}

export function useLogin() {

    return useMutation<AuthResponse, Error, LoginCredentials>({
        mutationFn: async ({ username, password }) => {
            return await api.post<AuthResponse>("/api/auth/login", { username, password }, false);
        },
        onSuccess: (data) => {
            queryClient.setQueryData(["authToken"], data.access_token);
            queryClient.setQueryData(["authUser"], data.user);

            // 🔹 Säkerställer att cache-data används direkt
            queryClient.invalidateQueries({queryKey: ["authToken"]});
        },
        onError: (error) => {
            console.error("Login failed:", error.message);
        },
    });
}

import { useQuery, useMutation, useQueries } from "@tanstack/react-query";
import api from "../services/apiClient";
import { User, profileImageOrigin as ProfileImageOrigin, ProfileImageInfo, UserProfile } from "../models/user";
import { queryClient } from "../services/queryClient";
import { useApiAuth } from "./useApiAuth";
import { AccessToken } from "../models/accessToken";
import { useContext } from "react";
import { AuthContext } from "../contexts/AuthContext";


export const useApiUser = () => {
    const { data: token } = useApiAuth();
    return useQuery<User, Error>({
        queryKey: ["authUser"],
        queryFn: async () => {
            if (!token) {
                throw new Error("Token data is undefined");
            }
            return await api.get<User>("/api/user", true, token.access_token);
        },
        staleTime: 1000 * 60 * 60,
        retry: true,
        enabled: !!token
    });
};

export const useApiUserProfile = (userId: number) => {
    const authContext = useContext(AuthContext);
    const token = authContext?.token;
    if (!token) {
        throw new Error("Token data is undefined");
    }
    return useQuery<UserProfile, Error>({
        queryKey: ["userProfile", userId],
        queryFn: async () => {
            if (!token) {
                throw new Error("Token data is undefined");
            }
            return await api.get<UserProfile>(`/api/user/${userId}`, true, token);
        },
        staleTime: 1000 * 60 * 60,
        retry: true,
        enabled: !!token
    });
};

export const useApiPublicUserProfile = (userId: number) => {
    return useQuery<UserProfile, Error>({
        queryKey: ["userPublicProfile", userId],
        queryFn: async () => {
            return await api.get<UserProfile>(`/api/user/public/${userId}`, false);
        },
        staleTime: 1000 * 60 * 60,
        retry: true,
    });
};

export const useSaveUserProfile = () => {

    return useMutation<void, Error, User>({
        mutationFn: async (user: User) => {
            const tokenPromise = queryClient.ensureQueryData<AccessToken>({ queryKey: ["authToken"] });
            const token = await tokenPromise;
            if (!token) throw new Error("Token saknas");

            await api.put<User>("/api/user/update", { user: user }, true, token.access_token);

        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["authUser"] });
        }
    });
};

export const useProfileImage = () => {
    const authContext = useContext(AuthContext);
    const token = authContext?.token;

    const results = useQueries({
        queries: [
            {
                queryKey: ["profileImageOrigin"],
                queryFn: async () => {
                    console.log("Fetching image blob");
                    return await fetch(`api/user/profileimage/origin`, {
                        headers: { Authorization: `Bearer ${token}` },
                    }).then((res) => res.blob());
                },
                staleTime: 1000 * 60 * 60,
                retry: false,
            },
            {
                queryKey: ["profileImageInfo"],
                queryFn: async () => {
                    return await api.get<ProfileImageInfo>('api/user/profileimage/origin/info', true, token ?? "");
                },
                staleTime: 1000 * 60 * 60,
                retry: false,
            }
        ],
    });
    const imageResult = results[0];
    const infoResult = results[1];

    const isLoading = imageResult.isLoading || infoResult.isLoading;
    const error = imageResult.error || infoResult.error;

    const image = imageResult.data;
    const info = infoResult.data;

    return {
        data: image && info ? { image, info } : undefined,
        isLoading,
        error
    };
};


export const useSaveProfileImageOrigin = () => {
    const authContext = useContext(AuthContext);
    return useMutation<void, Error, ProfileImageOrigin>({
        mutationFn: async ({ info, image }: ProfileImageOrigin) => {
            const token = authContext?.token;
            if (!token) {
                throw new Error("Token data is undefined");
            }

            if (image && image.size > 1024 * 1024 * 30) {
                throw new Error("Filen är för stor (max 30 MB)");
            }
            await api.post('api/user/profileimage/origin/info', info, true, token);

            if (image) {
                await api.postBlob("/api/user/profileimage/origin", image, true, token);
                queryClient.invalidateQueries({ queryKey: ['profileImageOrigin'] });
            }
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["authUser"] });
            queryClient.invalidateQueries({ queryKey: ['profileImageInfo'] });
        }
    });
};


export const useUserProfileImageThumbnail = () => {
    const authContext = useContext(AuthContext);
    return useMutation<void, Error, Blob>({
        mutationFn: async (imageBlob: Blob) => {
            const token = authContext?.token;
            if (!token) {
                throw new Error("Token data is undefined");
            }

            if (imageBlob.size > 1024 * 1024 * 50) {
                throw new Error("Filen är för stor (max 15 MB)");
            }
            await api.postBlob("/api/user/profileimage", imageBlob, true, token);

        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["authUser"] });
        },
        onError: (error) => {
            console.error("Error saving profile imagethumbnail:", error.message);
        }
    });
};

export const useToggleFollowUser = () => {
    const authContext = useContext(AuthContext);
    return useMutation<void, Error, { userId: number; startFollow: boolean }>({
        mutationFn: async ({ userId, startFollow }: { userId: number; startFollow: boolean }) => {
            const token = authContext?.token;
            if (!token) {
                throw new Error("Token data is undefined");
            }
            return startFollow ?
                await api.post<void>(`/api/user/follow/`, {followedId: userId }, true, token)
                : await api.delete<void>(`/api/user/unfollow/${userId}`, true, token);
        },
        onSuccess: (_data, { userId }: { userId: number; startFollow: boolean }) => {
            queryClient.invalidateQueries({ queryKey: ["userProfile", userId] });
            queryClient.invalidateQueries({ queryKey: ["userProfile", authContext?.user?.id] });
        }
    });
}
import { useInfiniteQuery} from "@tanstack/react-query";
import api from "../services/apiClient";
import type { Post, Posts } from "../models/post";

export const useApiPublicPosts = (userId: number | null = null) => {

  return useInfiniteQuery<Post[], Error>({
    queryKey: userId ? ["PublUserPosts", userId] : ["PublPosts"],
    initialPageParam: 0,
    queryFn: async ({ pageParam = 0 }) => {
          let url;
          if (!userId) {
            url = pageParam === 0
              ? `/api/post/public?limit=25`
              : `/api/post/public?afterId=${pageParam}&limit=5`;
          } else {
            url = pageParam === 0
              ? `/api/post/public/user/${userId}?limit=25`
              : `/api/post/public/user/${userId}?afterId=${pageParam}&limit=5`;
          }
      try {
        const posts = await api.get<Posts>(url, false);

        return posts.posts;
      } catch (error: unknown) {
        if (error instanceof Error && error.message.includes("404")) {
          return [];
        }
        throw error;
      }
    },
    getNextPageParam: (lastPage) => {
      if (!lastPage || lastPage.length === 0) return undefined;
      const ids = lastPage
        .filter((post) => post && typeof post.id === "number")
        .map((post) => post.id);

      if (ids.length === 0) return undefined;

      return Math.min(...ids);
    },
    staleTime: 1000 * 60 * 5,
    retry: false,
  });
};

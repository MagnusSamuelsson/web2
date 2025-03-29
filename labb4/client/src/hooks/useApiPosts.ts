import { useInfiniteQuery, useMutation, useQuery } from "@tanstack/react-query";
import api from "../services/apiClient";
import type { Post, Posts } from "../models/post";
import { queryClient } from "../services/queryClient";
import { Comment } from "../models/comment";
import { UploadedImage } from "../models/UploadedImage";
import { useContext } from "react";
import { AuthContext } from "../contexts/AuthContext";

export const useApiPosts = (userId: number | null = null) => {
  const authContext = useContext(AuthContext);

  return useInfiniteQuery<Post[], Error>({
    queryKey: userId ? ["AuthUserPosts", userId] : ["AuthPosts"],
    initialPageParam: 0,
    queryFn: async ({ pageParam = 0 }) => {
      let url;

      if (!userId) {
        url = pageParam === 0
          ? `/api/post?limit=25`
          : `/api/post?afterId=${pageParam}&limit=5`;
      } else {
        url = pageParam === 0
          ? `/api/post/user/${userId}?limit=25`
          : `/api/post/user/${userId}?afterId=${pageParam}&limit=5`;
      }

      if (!authContext?.token) throw new Error("Token saknas");
      try {
        const posts = await api.get<Posts>(url, true, authContext.token);
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

export const usePostById = (postId: number, userId: number | null = null) => {
  const authContext = useContext(AuthContext);

  return useQuery<Post, Error>({
    queryKey: ["post", postId],
    queryFn: async () => {
      if (!authContext?.token) throw new Error("Token saknas");

      const cachedAuthPosts = queryClient.getQueryData<{ pages: Post[][] }>(["AuthPosts"]);
      if (cachedAuthPosts) {
        for (const page of cachedAuthPosts.pages) {
          const foundPost = page.find((post) => post.id === postId);
          if (foundPost) return foundPost;
        }
      }

      if (userId !== null) {
        const cachedUserPosts = queryClient.getQueryData<{ pages: Post[][] }>(["AuthUserPosts", userId]);
        if (cachedUserPosts) {
          for (const page of cachedUserPosts.pages) {
            const foundPost = page.find((post) => post.id === postId);
            if (foundPost) return foundPost;
          }
        }
      }

      // 3. Om posten inte finns i cache, hämta den från API:et
      return await api.get<Post>(`/api/post/${postId}`, true, authContext.token);
    },
    staleTime: 1000 * 60 * 5,
    retry: false,
    enabled: !!postId,
  });
};

interface CreatePostInput {
  content: string;
  images: UploadedImage[];
}

export const useCreatePostMutation = () => {

  const authContext = useContext(AuthContext);
  return useMutation<Post, Error, CreatePostInput>({
    mutationFn: async ({ content, images }: CreatePostInput) => {
      const token = authContext?.token;
      if (!token) throw new Error("Token saknas");
      const user = authContext?.user;
      if (!user) throw new Error("User saknas");

      const { data: newPost } = await api.post<{ data: Post }>("/api/post/create", { content }, true, token);

      try {
        const uploadedImageNames = await Promise.all(
          images.map(async (image) => {
            await api.postBlob(`/api/post/${newPost.id}/uploadImage`, image.file, true, token);
            return image.url;
          })
        );
        newPost.user = user;
        newPost.images = uploadedImageNames;
        newPost.number_of_comments = 0;
        newPost.number_of_likes = 0;
        newPost.liked_by_current_user = false;

        return newPost;
      } catch {
        await api.delete(`/api/post/${newPost.id}/delete`, true, token);
        throw new Error("Failed to upload image");
      }
    },

    onMutate: async ({ content }) => {
      const user = authContext?.user;
      if (!user) throw new Error("User saknas");

      await queryClient.cancelQueries({ queryKey: ["AuthPosts"] });
      const previousData = queryClient.getQueryData<{ pages: Post[][] }>(["AuthPosts"]);

      const optimisticPost: Post = {
        id: -Math.floor(Math.random() * 100000),
        content,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        user_id: user.id ?? -1,
        user,
        images: [],
        number_of_comments: 0,
        number_of_likes: 0,
        liked_by_current_user: false,
      };

      queryClient.setQueryData(["AuthPosts"], (oldData: { pages: Post[][] } | undefined) => {
        if (!oldData) return { pages: [[optimisticPost]] };
        return {
          ...oldData,
          pages: [[optimisticPost, ...oldData.pages[0]], ...oldData.pages.slice(1)],
        };
      });

      return { previousData };
    },

    onError: (_err, _vars, context) => {
      const typedContext = context as { previousData?: { pages: Post[][] } };
      if (typedContext?.previousData) {
        queryClient.setQueryData(["AuthPosts"], typedContext.previousData);
      }
    },

    onSuccess: (newPost) => {
      queryClient.setQueryData(["AuthPosts"], (oldData: { pages: Post[][] } | undefined) => {
        if (!oldData) return { pages: [[newPost]] };
        return {
          ...oldData,
          pages: [[newPost, ...oldData.pages[0].filter(p => p.id > 0)], ...oldData.pages.slice(1)],
        };
      });
    },
  });
};



export const useLikePostMutation = () => {

  const authContext = useContext(AuthContext);

  return useMutation<void, Error, number>({
    mutationFn: async (postId: number) => {
      const token = authContext?.token;
      if (!token) throw new Error("Token saknas");

      return await api.post("/api/post/like", { postId }, true, token);
    },

    onMutate: async (postId) => {
      await queryClient.cancelQueries({ queryKey: ["AuthPosts"] });

      const prevData = queryClient.getQueryData<{ pages: Post[][] }>(["AuthPosts"]);

      queryClient.setQueryData(["AuthPosts"], (oldData: { pages: Post[][] } | undefined) => {
        if (!oldData) return oldData;
        return {
          ...oldData,
          pages: oldData.pages.map((page: Post[]) =>
            page.map((p) =>
              p.id === postId
                ? {
                  ...p,
                  liked_by_current_user: true,
                  number_of_likes: (p.number_of_likes || 0) + 1,
                }
                : p
            )
          ),
        };
      });

      return { prevData };
    },

    onError: (context: unknown) => {
      const typedContext = context as { previousData?: { pages: Post[][] } };
      if (typedContext?.previousData) {
        queryClient.setQueryData(["AuthPosts"], typedContext.previousData);
      }
    },
  });
};

export const useUnlikePostMutation = () => {

  const authContext = useContext(AuthContext);
  return useMutation<void, Error, number>({
    mutationFn: async (postId: number) => {
      const token = authContext?.token;
      if (!token) throw new Error("Token saknas");

      return await api.delete(`/api/post/${postId}/unlike`, true, token);
    },

    onMutate: async (postId) => {
      await queryClient.cancelQueries({ queryKey: ["AuthPosts"] });

      const prevData = queryClient.getQueryData<{ pages: Post[][] }>(["AuthPosts"]);

      queryClient.setQueryData(["AuthPosts"], (oldData: { pages: Post[][] } | undefined) => {
        if (!oldData) return oldData;
        return {
          ...oldData,
          pages: oldData.pages.map((page: Post[]) =>
            page.map((p) =>
              p.id === postId
                ? {
                  ...p,
                  liked_by_current_user: false,
                  number_of_likes: Math.max((p.number_of_likes || 1) - 1, 0),
                }
                : p
            )
          ),
        };
      });

      return { prevData };
    },

    onError: (context: unknown) => {
      const typedContext = context as { previousData?: { pages: Post[][] } };
      if (typedContext?.previousData) {
        queryClient.setQueryData(["AuthPosts"], typedContext.previousData);
      }
    },
  });
};

export const useToggleLikeMutation = () => {

  const authContext = useContext(AuthContext);
  return useMutation<void, Error, Post>({
    mutationFn: async (post) => {
      const token = authContext?.token;
      if (!token) throw new Error("Token saknas");
      console.log(post);
      if (post.liked_by_current_user) {
        await api.delete(`/api/post/${post.id}/unlike`, true, token);
      } else {
        await api.post("/api/post/like", { postId: post.id }, true, token);
      }
    },

    onMutate: async (post) => {
      await Promise.all([
        queryClient.cancelQueries({ queryKey: ["AuthPosts"] }),
        queryClient.cancelQueries({ queryKey: ["AuthUserPosts", post.user_id] }),
      ]);

      const previousAuth = queryClient.getQueryData<{ pages: Post[][] }>(["AuthPosts"]);
      const previousUser = queryClient.getQueryData<{ pages: Post[][] }>(["AuthUserPosts", post.user_id]);

      const updatePosts = (data: { pages: Post[][] } | undefined) => {
        if (!data) return data;
        return {
          ...data,
          pages: data.pages.map((page) =>
            page.map((p) =>
              p.id === post.id
                ? {
                  ...p,
                  liked_by_current_user: !p.liked_by_current_user,
                  number_of_likes: p.liked_by_current_user
                    ? Math.max((p.number_of_likes || 1) - 1, 0)
                    : (p.number_of_likes || 0) + 1,
                }
                : p
            )
          ),
        };
      };
      console.log(previousUser);
      queryClient.setQueryData(["AuthPosts"], updatePosts(previousAuth));
      queryClient.setQueryData(["AuthUserPosts", post.user_id], updatePosts(previousUser));

      return { previousAuth, previousUser, post };
    },

    onError: (context: unknown) => {
      const typedContext = context as { previousData?: { pages: Post[][] } };
      if (typedContext?.previousData) {
        queryClient.setQueryData(["AuthPosts"], typedContext.previousData);
      }
    },
  });
};

export const useDeletePostMutation = () => {
  const authContext = useContext(AuthContext);

  return useMutation<number, Error, number>({
    mutationFn: async (postId) => {
      const token = authContext?.token;
      if (!token) throw new Error("Token saknas");

      await api.delete(`/api/post/${postId}/delete`, true, token);
      return postId;
    },

    onSuccess: async (postId) => {
      await queryClient.cancelQueries({ queryKey: ["AuthPosts"] });
      const prevData = queryClient.getQueryData<{ pages: Post[][] }>(["AuthPosts"]);
      queryClient.setQueryData(["AuthPosts"], (oldData: { pages: Post[][] } | undefined) => {
        if (!oldData) return oldData;
        return {
          ...oldData,
          pages: oldData.pages.map((page: Post[]) => page.filter((p) => p.id !== postId)),
        };
      });

      return { prevData };
    },

    onError: (context: unknown) => {
      const typedContext = context as { previousData?: { pages: Post[][] } };
      if (typedContext?.previousData) {
        queryClient.setQueryData(["AuthPosts"], typedContext.previousData);
      }
    },
  });
}

const buildCommentTree = (comments: Comment[]): Comment[] => {
  const map = new Map<number, Comment>();
  const root: Comment[] = [];

  comments.forEach((c) => map.set(c.id, { ...c, answers: [] }));

  comments.forEach((c) => {
    if (c.reply_comment_id && map.has(c.reply_comment_id)) {
      map.get(c.reply_comment_id)!.answers.push(map.get(c.id)!);
    } else {
      root.push(map.get(c.id)!);
    }
  });

  return root;
};

export const usePostComments = (postId: number) => {

  const authContext = useContext(AuthContext);
  return useQuery<Comment[], Error>({
    queryKey: ["postComments", postId],
    queryFn: async () => {
      const token = authContext?.token;
      if (!token) throw new Error("Token saknas");

      const response = await api.get<{ comments: Comment[] }>(
        `/api/comment/${postId}`,
        true,
        token
      );
      console.log(response.comments);
      return buildCommentTree(response.comments);
    },
    staleTime: 1000 * 60,
    retry: false,
    enabled: !!postId,
  });
};


interface CreateCommentInput {
  postId: number;
  content: string;
  replyTo?: number | null;
}
export const useCreateCommentMutation = () => {

  const authContext = useContext(AuthContext);

  return useMutation<Comment, Error, CreateCommentInput>({
    mutationFn: async ({ postId, content, replyTo }) => {
      const token = authContext?.token;
      if (!token) throw new Error("Token saknas");

      const url = replyTo ? "/api/comment/answer" : "/api/comment/create";

      const response = await api.post<Comment>(
        url,
        {
          post_id: postId,
          content,
          reply_comment_id: replyTo,
        },
        true,
        token
      );
      return response;
    },

    onSuccess: (_comment, { postId }) => {
      queryClient.invalidateQueries({ queryKey: ["postComments", postId] });
    },
  });
};
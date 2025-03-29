import LoadingSpinner from "../../components/LoadingSpinner/LoadingSpinner";
import PostInList from "../../components/PostInList/PostInList";
import { useApiPublicPosts } from "../../hooks/useApiPublicPosts";
import InfiniteScroll from "../../layouts/InfiniteScroll";
import styles from "./PostsPage.module.css";

export default function PostsPage() {
  const {
    data,
    isLoading,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useApiPublicPosts();
  const posts = data?.pages.flat() ?? [];

  if (isLoading) {
    return <LoadingSpinner text="Loading posts..." />;
  }

  return (
    <div className={styles.postList}>
      <InfiniteScroll fetchNextPage={fetchNextPage} hasNextPage={hasNextPage}>
        {posts.map((post) => (
          <PostInList key={post.id} post={post} />
        ))}
      </InfiniteScroll>
      {isFetchingNextPage && <LoadingSpinner text="Loading posts..." />}

      {!hasNextPage && <p>No more posts to show</p>}

    </div>
  );
}

import LoadingSpinner from "../../components/LoadingSpinner/LoadingSpinner";
import NewPost from "../../components/NewPost/NewPost";
import PostInList from "../../components/PostInList/PostInList";
import { useApiPosts } from "../../hooks/useApiPosts";
import InfiniteScroll from "../../layouts/InfiniteScroll";
import styles from "./PostsPage.module.css";

export default function PostsPageAuth() {
  const {
    data,
    isLoading,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useApiPosts();
  const posts = data?.pages.flat() ?? [];

  if (isLoading) {
    return <LoadingSpinner text="Loading posts..." />;
  }

  return (

    <>

      <NewPost />

      <div className={styles.postList}>
        <InfiniteScroll fetchNextPage={fetchNextPage} hasNextPage={hasNextPage}>
          {posts.map((post) => (
            <PostInList key={post.id} post={post} />
          ))}
        </InfiniteScroll>
        {!hasNextPage && <p>No more posts to show</p>}
        {isFetchingNextPage && <LoadingSpinner text="Loading posts..." />}
      </div>
    </>
  );
}

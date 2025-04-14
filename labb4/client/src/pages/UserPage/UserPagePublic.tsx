import LoadingSpinner from "../../components/LoadingSpinner/LoadingSpinner";
import PostInList from "../../components/PostInList/PostInList";
import InfiniteScroll from "../../layouts/InfiniteScroll";
import styles from "./UserPage.module.css";
import { useParams } from "react-router-dom";
import { useApiPublicPosts } from "../../hooks/useApiPublicPosts";
import { useApiPublicUserProfile } from "../../hooks/useApiUser";
import { useNavigate } from "react-router-dom";
import TwoLineInfo from "../../components/TwoLineInfo/TwoLineInfo";

export default function UserPage() {
  const { id: userId } = useParams();
  const {
    data,
    isLoading,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useApiPublicPosts(userId ? parseInt(userId) : null);

  const profileuserId = parseInt(userId ?? "");
  const { data: userProfile, isLoading: loadingUser } = useApiPublicUserProfile(profileuserId);
  const posts = data?.pages.flat() ?? [];
  const navigate = useNavigate();

  if (isLoading || loadingUser) {
    return <LoadingSpinner text="Loading posts..." />;
  }

  if (!userProfile) {
    navigate("/");
    return null;
  }
  return (
    <>
    <div className={styles.container}>
        <img
          src={`/api/profileimage/${userProfile.profile_image}`}
          alt={userProfile.username}
          className={styles.profileImage}
        />
        <div className={styles.rightCol}>
          <div className={styles.topRow}>
          <h2 className={styles.usernameHeader}>{userProfile.username}</h2>
          </div>
          <div className={styles.info}>
            <TwoLineInfo className={styles.infoBox} top={userProfile.number_of_posts.toString()} bottom="Posts" />
            <TwoLineInfo className={styles.infoBox} top={userProfile.number_of_followers.toString()} bottom="Followers" />
            <TwoLineInfo className={styles.infoBox} top={userProfile.number_of_following.toString()} bottom="Following" />
          </div>
          <div className={styles.description}>
            {userProfile.description}
          </div>
        </div>
      </div>
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

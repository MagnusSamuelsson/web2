import { useContext } from "react";
import { useNavigate } from "react-router-dom";
import LoadingSpinner from "../../components/LoadingSpinner/LoadingSpinner";
import PostInList from "../../components/PostInList/PostInList";
import { useApiPosts } from "../../hooks/useApiPosts";
import InfiniteScroll from "../../layouts/InfiniteScroll";
import { Post } from "../../models/post";
import styles from "./UserPage.module.css";
import { useParams } from "react-router-dom";
import { useApiUserProfile, useToggleFollowUser } from "../../hooks/useApiUser";
import TwoLineInfo from "../../components/TwoLineInfo/TwoLineInfo";
import { AuthContext } from "../../contexts/AuthContext";

export default function UserPage() {
  const navigate = useNavigate();
  const { id: userId } = useParams();
  const authContext = useContext(AuthContext);

  const profileuserId = parseInt(userId ?? "");
  const { data: userProfile, isLoading: loadingUser } = useApiUserProfile(profileuserId);
  const { mutate: toggleFollowUser } = useToggleFollowUser();

  const {
    data,
    isLoading,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useApiPosts(userId ? parseInt(userId) : null);
  const posts = data?.pages.flat() as Post[] ?? [];

  if (isLoading || loadingUser) {
    return <LoadingSpinner text="Loading posts..." />;
  }
  const toggleFollow = async () => {
    if (!userProfile || !userProfile.id  || userProfile.is_followed_by_current_user === undefined) return;
      toggleFollowUser({
        userId: userProfile.id,
        startFollow: !userProfile.is_followed_by_current_user
      });
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
            {userProfile.id !== authContext?.user?.id
              && <button
                onClick={toggleFollow}
                className={styles.followButton}>
                {userProfile.is_followed_by_current_user ? "unfollow" : userProfile.is_following_current_user ? "follow back" : "follow"}
              </button>}
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

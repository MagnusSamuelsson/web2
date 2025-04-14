import { Post } from "../../models/post";
import { useContext, useRef, useState } from "react";
import HeartSymbol from "../Symbols/HeartSymbol";
import CommentSymbol from "../Symbols/CommentSymbol";
import styles from "./PostInList.module.css";
import { AuthContext } from "../../contexts/AuthContext";
import { useDeletePostMutation, useToggleLikeMutation } from "../../hooks/useApiPosts";
import { useAuthModal } from "../../stores/useAuthModal";
import { IoIosMore } from "react-icons/io";
import ImageCarousel from "../ImageCarousel/ImageCarousel";
import { useNavigate } from "react-router-dom";
import UserPageLink from "../Links/UserPageLink";

export default function PostInList({ post }: { post: Post }) {

  const auth = useContext(AuthContext);
  const authenticated = auth?.authenticated;
  const user = auth?.user;
  const toggleLike = useToggleLikeMutation();
  const { mutate: deletePost } = useDeletePostMutation();
  const [showDropdown, setShowDropdown] = useState(false);
  const { open: openAuthModal } = useAuthModal();
  const navigate = useNavigate();
  const dropdownRef = useRef<HTMLUListElement | null>(null);
  const morMenuRef = useRef<HTMLButtonElement | null>(null);
  if (!post.user) {
    return null;
  }

  const profile_image: string = '/api/profileimage/' + post.user.profile_image;
  const timeDifference: number = Math.floor((Date.now() - Date.parse(post.created_at)) / 1000);
  const edited = post.updated_at !== post.created_at;

  const handleHeartClick = async () => {
    toggleLike.mutate(post);
  }

  const handleClickOutside = (event: MouseEvent) => {
    if (morMenuRef.current && morMenuRef.current.contains(event.target as Node)) {
      return;
    }
    if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
      showDropdownHandler(false);
    }
  };

  const showDropdownHandler = (requestOpen: boolean) => {
    if (requestOpen) {
      setShowDropdown(requestOpen);
      document.addEventListener("mousedown", handleClickOutside);
    } else {
      setShowDropdown(requestOpen);
      document.removeEventListener("mousedown", handleClickOutside);
    }
  }

  let timeAgo: string = '';
  if (timeDifference < 60) {
    timeAgo = `${timeDifference} seconds ago`;
  } else if (timeDifference < 3600) {
    timeAgo = `${Math.floor(timeDifference / 60)} minute${Math.floor(timeDifference / 60) !== 1 ? 's' : ''} ago`;
  } else if (timeDifference < 86400) {
    timeAgo = `${Math.floor(timeDifference / 3600)} hour${Math.floor(timeDifference / 3600) !== 1 ? 's' : ''} ago`;
  } else if (timeDifference < 604800) {
    timeAgo = `${Math.floor(timeDifference / 86400)} day${Math.floor(timeDifference / 86400) !== 1 ? 's' : ''} ago`;
  } else {
    timeAgo = `${Math.floor(timeDifference / 604800)} week${Math.floor(timeDifference / 604800) !== 1 ? 's' : ''} ago`;
  }
  return (
    <div className={styles.post}>
      <img
        src={profile_image}
        alt={`Profilbild för ${post.user.username}`}
        className={styles.profileImage}
      />
      <div className={styles.rightCol}>
        <div className={styles.topRow}>
          <UserPageLink user={post.user} />
          <span>
            {timeAgo}
            {edited && <>(edited)</>}
          </span>
          <div className={styles.dropdownWrapper}>
            <button
              ref={morMenuRef}
              className={styles.moreButton}
              aria-label="More options"
              onClick={() => {
                console.log('clicked');
                showDropdownHandler(!showDropdown);
              }}>
              <IoIosMore />
            </button>
            {showDropdown && <ul
              className={styles.dropdown}
              ref={dropdownRef}
            >
              {post.user_id === user?.id &&
                <>
                  <li className={styles.dropdownItem}>
                    <button
                      aria-label="Edit this post"
                      className={styles.dropdownButton}
                      onClick={() => {
                        setShowDropdown(false);
                        alert('Not implemented');
                      }}>
                      Edit
                    </button>
                  </li>
                  <li className={styles.dropdownItem}>
                    <button
                      aria-label="Delete this post"
                      className={styles.dropdownButton}
                      onClick={() => {
                        setShowDropdown(false);
                        deletePost(post.id);
                      }}>
                      Delete
                    </button>
                  </li>
                </>}
              {post.user_id !== user?.id && <li className={styles.dropdownItem}>
                <button
                  aria-label="Report this post"
                  className={styles.dropdownButton}
                  onClick={() => {
                    setShowDropdown(false);
                    alert('Not implemented');
                  }}>
                  Report
                </button>
              </li>}
            </ul>}
          </div>
        </div>
        {post.images && <ImageCarousel images={post.images} postId={post.id} />}
        <div className={styles.postContent}>
          {post.content}
        </div>
        {authenticated && <div className={styles.bottomRow}>
          <span className={styles.symbolNumber}>
            <button aria-label="Like" className={styles.symbolButton} onClick={handleHeartClick}>
              <HeartSymbol className={`${styles.heartSymbol} ${post.liked_by_current_user ? styles.liked : styles.unliked}`} />
            </button>
            {post.number_of_likes}
          </span>
          <span className={styles.symbolNumber}>
            <button
              aria-label="Comment"
              onClick={() => {
                navigate(`/post/${post.id}`);
                window.scrollTo(0, 0);
              }}
              className={styles.symbolButton}>
              <CommentSymbol className={styles.commentSymbol} />
            </button>
            {post.number_of_comments}
          </span>
        </div>}
        {!authenticated && <div className={styles.bottomRow}>
          <span className={styles.likes}>
            <button aria-label="Like" className={styles.symbolButton} onClick={openAuthModal}>
              <HeartSymbol className={`${styles.heartSymbol} ${styles.unliked}`} />
            </button>
            {post.number_of_likes}
          </span>
          <span>
            <button aria-label="Comment" onClick={openAuthModal} className={styles.symbolButton}>
              <CommentSymbol className={styles.commentSymbol} />
            </button>
            {post.number_of_comments}
          </span>
        </div>}
      </div>
    </div >
  );
}

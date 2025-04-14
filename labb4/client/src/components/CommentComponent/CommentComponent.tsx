import {Comment} from "../../models/comment";
import {Post} from "../../models/post";
import CommentInput from "../CommentInput/CommentInput";
import { useState } from "react";
import styles from './CommentComponent.module.css';
import { FaReply } from "react-icons/fa";
import UserPageLink from "../Links/UserPageLink";

interface CommentProps {
  comment: Comment;
  comments: Comment[];
  answerTo?: string;
  setComments?: React.Dispatch<React.SetStateAction<Comment[]>>;
  post: Post;
}

const CommentComponent: React.FC<CommentProps> = ({
  comment,
  comments,
  answerTo = null,
  post
}) => {
  const profile_image: string = '/api/profileimage/' + comment.user.profile_image;
  const timeDifference: number = Math.floor((Date.now() - Date.parse(comment.created_at)) / 1000);
  const [reply, setReply] = useState<boolean>(false);
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
    <div className={`${styles.commentcomponent} ${answerTo != null ? styles.nested : ''}`}>
      <div className={styles.comment}>
        <img
          src={profile_image}
          alt={comment.user.username}
          className={styles.profileImage}
        />
        <div className={styles.rightCol}>
          <div className={styles.topRow}>
            <UserPageLink
              user={comment.user}
             />
            <span className={styles.timestamp}>{timeAgo}</span>
          </div>
          <div className={styles.content}>
            {answerTo && '@' + answerTo + ': '}
            {comment.content}
          </div>
          <div className={styles.bottomRow}>
            <span onClick={() => setReply(!reply)} >
              <FaReply />
            </span>
          </div>
          {reply && <CommentInput
            commentId={comment.id}
            onReply={() => setReply(!reply)}
            post={post}
            placeHolder="Write a reply..."
          />}
        </div>
      </div>

      {comment.answers.length > 0 && (
        <div className={styles.answers}>
          {comment.answers.map((answer: Comment) => (
            <CommentComponent
              key={'answer-' + answer.id}
              comment={answer}
              answerTo={comment.user.username}
              comments={comments}
              post={post}
            />
          ))}
        </div>
      )}
    </div>
  );
};

export default CommentComponent;
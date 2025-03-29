
import {Post} from '../../models/post';
import styles from './CommentInput.module.css';
import { TextAreaForm } from '../TextAreaForm/TextAreaForm';
import { useCreateCommentMutation } from '../../hooks/useApiPosts';

interface CommentInputProps {
  commentId?: number | null;
  placeHolder: string;
  onReply?: () => void;
  post: Post;
}

export default function CommentInput({
  commentId = null,
  placeHolder = "",
  onReply,
  post,
}: CommentInputProps) {
  const createComment = useCreateCommentMutation();

  async function submitReply(form: HTMLFormElement): Promise<boolean> {
    const textarea = form.elements.namedItem('content') as HTMLTextAreaElement;
    const content = textarea.value.trim();
    if (!content) return false;

    if (onReply) onReply();

    try {
      await createComment.mutateAsync({
        postId: post.id,
        content,
        replyTo: commentId,
      });

      post.number_of_comments++; // OBS: du kan också flytta denna till post-cache

      return true;
    } catch (error) {
      console.error("Failed to submit comment:", error);
      return false;
    }
  }

  return (
    <div className={styles.reply}>
      <TextAreaForm
        onSubmit={submitReply}
        placeholder={placeHolder}
      />
    </div>
  );
}

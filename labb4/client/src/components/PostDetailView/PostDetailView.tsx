import styles from "./PostDetailView.module.css";
import CommentComponent from "../CommentComponent/CommentComponent";
import CommentInput from "../CommentInput/CommentInput";
import { usePostById, usePostComments } from "../../hooks/useApiPosts";
import ImageCarousel from "../ImageCarousel/ImageCarousel";
import LoadingSpinner from "../LoadingSpinner/LoadingSpinner";
import { useParams } from "react-router-dom";
import { useNavigate } from "react-router-dom";
import UserPageLink from "../Links/UserPageLink";


export default function PostDetailView() {
    const navigate = useNavigate();
    const {id: postIdParam} = useParams();
    const postId = parseInt(postIdParam ?? "");
    const {
        data: comments,
        isLoading: isLoadingComments,
        isError,
    } = usePostComments(postId);

    const {
        data: post,
        isLoading: isLoadingPost
    } = usePostById(postId);

    if (isLoadingPost) return <LoadingSpinner text="Loading post..." />;

    if (!post) {
        navigate("/");
        return null;
    }

    if (!post.user) return null;

    const profile_image = "/api/profileimage/" + post.user.profile_image;

    return (
        <>
            <div className={styles.user}>
                <img src={profile_image} alt={post.user.username} className={styles.profileImage} />
                <div className={styles.rightCol}>
                    <UserPageLink user={post.user} />

                    {post.images && <ImageCarousel images={post.images} postId={post.id} />}
                    <div className={styles.content}>{post.content}</div>


                    <CommentInput
                        post={post}
                        placeHolder="Write a comment..."
                    />
                </div>
            </div>
            <div className={styles.border}>Comments:</div>
            <div className={styles.content}>
                {isLoadingComments && <p>Loading comments...</p>}
                {isError && <p>Failed to load comments.</p>}
                {comments &&
                    comments.map((comment) => (
                        <CommentComponent
                            key={`comment-${comment.id}`}
                            comments={comments}
                            comment={comment}
                            post={post}
                        />
                    ))}
            </div>
        </>
    );
}

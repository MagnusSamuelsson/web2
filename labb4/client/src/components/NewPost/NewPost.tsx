import { useCreatePostMutation } from "../../hooks/useApiPosts";
import styles from './NewPost.module.css';
import { TextAreaForm } from "../TextAreaForm/TextAreaForm";
import { useApiUser } from "../../hooks/useApiUser";
import { UploadedImage } from '../../models/UploadedImage';
import { useState } from 'react';
import UserPageLink from "../Links/UserPageLink";

export default function NewPost() {
    const { data: user, isLoading } = useApiUser();
    const createPost = useCreatePostMutation();
    const [images, setImages] = useState<UploadedImage[]>([]);

    if (isLoading) {
        return <div>Loading...</div>;
    }
    if (!user) {
        return <div>User not found</div>;
    }
    const profile_image = `/api/profileimage/${user.profile_image}`;

    async function handleSubmit(form: HTMLFormElement): Promise<boolean> {
        const textarea = form.elements.namedItem('content') as HTMLTextAreaElement;
        const content = textarea.value.trim();

        if (!content) return false;

        try {
            await createPost.mutateAsync({
                content,
                images: images
            });
            return true;
        } catch (error) {
            console.error("Error creating post:", error);
            return false;
        }
    }

    return (
        <div className={styles.container}>
            <img
                src={profile_image}
                alt={user.username}
                className={styles.profileImage}
            />
            <div className={styles.rightCol}>
                <div className={styles.topRow}>
                    <UserPageLink user={user} />
                </div>
                <TextAreaForm
                    onSubmit={handleSubmit}
                    placeholder="What's on your mind?"
                    hasImageButton={true}
                    images={images}
                    setImages={setImages}
                />
            </div>
        </div>
    );
}

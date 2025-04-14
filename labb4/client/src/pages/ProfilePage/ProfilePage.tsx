import { FaUser, FaSignOutAlt, FaRegSave } from "react-icons/fa";
import { NavigateFunction, useNavigate } from "react-router-dom";
import { TextAreaForm } from "../../components/TextAreaForm/TextAreaForm";
import { useState } from "react";
import ImageUploader from "../../components/ImageUploader/ImageUploader";
import styles from "./ProfilePage.module.css";
import api from "../../services/apiClient";

import { queryClient } from "../../services/queryClient";
import { useApiUser, useProfileImage, useSaveUserProfile } from "../../hooks/useApiUser";
import LoadingSpinner from "../../components/LoadingSpinner/LoadingSpinner";
import { User } from "../../models/user";



export default function ProfilePage() {
    const [showImageUploader, setShowImageUploader] = useState(false);
    const { data: userData, isLoading: userLoading } = useApiUser();
    const { mutate: saveUserProfile } = useSaveUserProfile();
    const user = userData
    const navigate = useNavigate();

    const { data: profileImageOrigin } = useProfileImage();
    async function handleSaveProfile(form: HTMLFormElement): Promise<boolean> {
        const textarea = form.elements.namedItem('content') as HTMLTextAreaElement;
        if (!textarea || !user) {
            return false;
        }
        const newUser: User = {
            ...user,
            description: textarea.value,
        };
        saveUserProfile(newUser);
        return true;
    };

    async function handleLogout(navigate: NavigateFunction) {
        await api.post("/api/auth/logout", {}, false);
        queryClient.setQueryData(["authToken"], null);
        queryClient.setQueryData(["authUser"], null);
        queryClient.clear();
        await navigate("/");
    }
    if (userLoading) {
        return <LoadingSpinner />;
    }

    if (!user) {
        return <div>Det gick inte att hämta användarinformation</div>;
    }

    return (
        <div className={styles.container}>
            <h2 className={styles.title}>
                <FaUser />  {user.username}
            </h2>
            <div className={styles.imageContainer}>
                {user.profile_image &&
                    <img
                        src={"/api/profileimage/" + user.profile_image}
                        alt="Profile"
                        className={styles.profileImage}
                        onClick={() => setShowImageUploader(true)}
                    />
                }
            </div>
            <div>
                {showImageUploader && <ImageUploader
                    onClose={() => setShowImageUploader(false)}
                    profileImageOrigin={profileImageOrigin}
                />}
                <label className={styles.label}>Profilbeskrivning:</label>
                <TextAreaForm
                    onSubmit={handleSaveProfile}
                    placeholder="Skriv en kort beskrivning om dig själv"
                    initialValue={user.description}
                    Symbol={FaRegSave}
                    keepHeight={true}
                />
            </div>

            <button className={`${styles.button} ${styles.logoutButton}`} onClick={() => handleLogout(navigate)}>
                <FaSignOutAlt /> Logga Ut
            </button>
        </div>
    );
}

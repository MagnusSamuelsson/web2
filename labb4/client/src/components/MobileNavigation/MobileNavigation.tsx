import { Link } from "react-router-dom";
import styles from "./MobileNavigation.module.css";
import { useContext } from "react";
import { useAuthModal } from '../../stores/useAuthModal';
import { FaHome } from "react-icons/fa";
import { CgProfile } from "react-icons/cg";
import { MdOutlineLogin } from "react-icons/md";
import { AuthContext } from "../../contexts/AuthContext";

export default function MobileNavigation() {
    const authContext = useContext(AuthContext);

    const { open: openLoginModal } = useAuthModal();

    return (
        <>
            <nav className={styles.nav} aria-label="Main">
                <ul className={styles.navList}>
                    <li className={styles.navItem}>
                        <Link
                            className={styles.link} to="/"
                            aria-label="Navigate home"
                            onClick={() => { window.scrollTo(0, 0) }}
                        >
                            <FaHome className={styles.icon} size={30} />
                        </Link>
                    </li>
                    {!authContext?.authenticated &&
                        <li className={styles.navItem}>
                            <button
                                aria-label="Open login/registration modal"
                                className={`${styles.linkButton} ${styles.link}`}
                                onClick={() => { openLoginModal() }
                                }>
                                <MdOutlineLogin className={styles.icon} size={30} />
                            </button>
                        </li>
                    }
                    {authContext?.authenticated &&
                        <li className={styles.navItem}>

                            <Link
                                aria-label="Navigate to profilesettings"
                                className={styles.link} to="/profile"
                                onClick={() => { window.scrollTo(0, 0) }}
                            >
                                <CgProfile className={styles.icon} size={30} />
                            </Link>
                        </li>
                    }
                </ul>
            </nav>
        </>
    );
}

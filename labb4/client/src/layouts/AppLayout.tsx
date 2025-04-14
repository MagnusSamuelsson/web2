import { Outlet } from "react-router-dom";
import MobileNavigation from "../components/MobileNavigation/MobileNavigation";

import AuthModal from '../components/AuthModal/AuthModal'
import styles from './AppLayout.module.css'
import { useAuthModal } from '../stores/useAuthModal'
export function AppLayout() {
  const { isOpen } = useAuthModal();
  return (
    <>

      <MobileNavigation />
      <header>
        <h1 className={styles.header}>Labb4a Blogg</h1>

      </header>
      <main>
        {isOpen && <AuthModal />}
        <Outlet />
      </main>
      <footer>
      </footer>
    </>
  );
}

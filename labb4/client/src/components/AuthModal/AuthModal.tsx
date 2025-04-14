import ModalLoginForm from './ModalLoginForm';
import ModalRegisterForm from './ModalRegisterForm';
import { useState } from 'react';
import styles from './AuthModal.module.css';
import { useAuthModal } from '../../stores/useAuthModal';



const AuthModal: React.FC = () => {

  const [showRegister, setShowRegister] = useState(false);
  const title = showRegister ? 'Register' : 'Log in';
  const { isOpen, close } = useAuthModal();
  if (!isOpen) return null;

  return (
    <div className={styles.overlay}>
      <div className={styles.content}>
        <h2>{title}</h2>
        {!showRegister && <ModalLoginForm onSwitch={() => { setShowRegister(true) }} onClose={close} />}
        {showRegister && <ModalRegisterForm onSwitch={() => { setShowRegister(false) }} onClose={close} />}
        <button onClick={close} className={styles.closeButton}>
          Close
        </button>
      </div>
    </div>
  );
};

export default AuthModal;

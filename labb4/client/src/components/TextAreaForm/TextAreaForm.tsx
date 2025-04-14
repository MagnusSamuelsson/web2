import styles from './TextAreaForm.module.css';
import { useRef, useState } from 'react';
import { IconType } from 'react-icons';
import { GrSend, GrImage } from 'react-icons/gr';
import Cropper, { Area } from 'react-easy-crop';
import getCroppedImg from '../../services/cropImage';
import { UploadedImage } from '../../models/UploadedImage';
import { FaRegSave } from "react-icons/fa";
import { MdOutlineCancel } from "react-icons/md";


interface TextAreaFormProps {
    placeholder: string;
    onSubmit: (form: HTMLFormElement) => Promise<boolean>;
    initialValue?: string;
    Symbol?: IconType;
    keepHeight?: boolean;
    hasImageButton?: boolean;
    images?: UploadedImage[];
    setImages?: React.Dispatch<React.SetStateAction<UploadedImage[]>>
}
export function TextAreaForm({
    onSubmit,
    initialValue = "",
    keepHeight = false,
    placeholder = "",
    hasImageButton = false,
    Symbol = GrSend,
    images = [],
    setImages = () => { },
}: TextAreaFormProps) {
    const [textAreaRows, setTextAreaRows] = useState<number>(1);
    const [textAreaValue, setTextAreaValue] = useState<string>(initialValue);
    const [imageButton, setImageButton] = useState<boolean>(false);

    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const formRef = useRef<HTMLFormElement | null>(null);
    const textareaRef = useRef<HTMLTextAreaElement | null>(null);

    const [cropImage, setCropImage] = useState<string | null>(null);
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState<Area | null>(null);

    const onImageClick = () => {
        fileInputRef.current?.click();
    }
    const onCropComplete = (_: Area, croppedAreaPixels: Area) => {
        setCroppedAreaPixels(croppedAreaPixels);
    }

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (ev) => {
            if (ev.target?.result) {
                setCropImage(ev.target.result as string);
            }
        };
        reader.readAsDataURL(file);
    }
    const handleDeleteImage = (index: number) => {
        setImages(prev => {
            const imageToRemove = prev[index];
            if (imageToRemove) {
                URL.revokeObjectURL(imageToRemove.url);
            }
            return prev.filter((_, i) => i !== index);
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const form = e.target as HTMLFormElement;
        onSubmit(form)
            .then((success) => {
                if (!success) {
                    return;
                }

                const textarea = form.elements.namedItem("content") as HTMLTextAreaElement;
                setImages([]);
                if (!keepHeight) {
                    setTextAreaValue('');
                    textarea.style.height = 'auto';
                    setTextAreaRows(1);
                    setImageButton(false);
                }

                document.removeEventListener('mousedown', handleClickOutside);
                document.removeEventListener('touchstart', handleClickOutside as EventListener);
            });
    }

    const handleClickOutside = (event: MouseEvent | TouchEvent) => {
        if (
            formRef.current &&
            !formRef.current.contains(event.target as Node)
        ) {
            if (textareaRef.current?.value === '') {
                textareaRef.current!.style.height = 'auto';
                setTextAreaRows(1);
                setImageButton(false);
                document.removeEventListener('mousedown', handleClickOutside);
                document.removeEventListener('touchstart', handleClickOutside as EventListener);
            }
        }
    }

    const textAreaFocus = () => {
        setTextAreaRows(5);
        setImageButton(true);
        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('touchstart', handleClickOutside as EventListener);
    }

    const adjustHeight = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
        const textarea = e.target;
        if (textarea.value.length > 500 || (textarea.value.match(/\n/g) || []).length > 6) {
            return
        }
        textarea.style.height = 'auto';
        textarea.style.height = `${textarea.scrollHeight - 15}px`;
        setTextAreaValue(textarea.value);
    }

    return (
        <form ref={formRef} className={styles.form} onSubmit={handleSubmit}>
            {cropImage && (
                <div className={styles.cropWrapper}>
                    <div className={styles.cropContainer}>
                        <Cropper
                            image={cropImage}
                            crop={crop}
                            zoom={zoom}
                            aspect={1}
                            onCropChange={setCrop}
                            onZoomChange={setZoom}
                            onCropComplete={onCropComplete}
                        />
                    </div>
                    <div className={styles.buttonContainer}>
                        <button
                            className={styles.cropButton}
                            type="button"
                            onClick={async () => {
                                if (!cropImage || !croppedAreaPixels) return;

                                const blob = await getCroppedImg(
                                    await (await fetch(cropImage)).blob(),
                                    croppedAreaPixels,
                                    0
                                );

                                if (blob) {
                                    const url = URL.createObjectURL(blob);
                                    setImages(prev => [...prev, { file: blob, url }]);
                                }

                                setCropImage(null);
                            }}
                        >
                            <FaRegSave />
                        </button>
                        <button
                            className={styles.cropButton}
                            onClick={() => setCropImage(null)}>
                            <MdOutlineCancel />
                        </button>
                    </div>
                </div>
            )}
            <input
                type="file"
                accept="image/*"
                ref={fileInputRef}
                onChange={handleFileChange}
                style={{ display: 'none' }}
            />
            {images.length > 0 && <div className={styles.imageUploaderWrapper}>
                <div className={styles.imageUploaderContainer}>
                    {images.map((img, i) => (
                        <div key={i} className={styles.imageTnWrapper}>
                            <img src={img.url} className={styles.imageUploadTn} alt={`uploaded-${i}`} />
                            <button
                                className={styles.deleteButton}
                                onClick={() => handleDeleteImage(i)}
                                aria-label="Delete image"
                            >
                                ×
                            </button>
                        </div>
                    ))}

                </div>
            </div>}
            <div className={styles.textAreaContainer} >
                <textarea
                    name='content'
                    ref={textareaRef}
                    value={textAreaValue}
                    className={styles.textarea}
                    onFocus={textAreaFocus}
                    onInput={adjustHeight}
                    rows={textAreaRows}
                    placeholder={placeholder}
                >
                </textarea>
                <div className={styles.buttonGroup}>

                    {imageButton && hasImageButton && images.length < 8 && <button
                        id="addImageButton"
                        className={styles.submitButton}
                        type="button"
                        onMouseDown={onImageClick}
                    >
                        <GrImage className={styles.submit} />
                    </button>}
                    <button
                        id="newPostButton"
                        className={styles.submitButton}
                        type="submit"
                    >
                        <Symbol className={styles.submit} />
                    </button>
                </div>
            </div>
        </form>
    );
}
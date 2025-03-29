import { Area, Point } from "react-easy-crop";
import { FaArrowsRotate } from "react-icons/fa6";
import { FaSave, FaImage } from "react-icons/fa";
import { IoMdCloseCircleOutline } from "react-icons/io";
import { profileImageOrigin } from "../../models/user";
import { useSaveProfileImageOrigin, useUserProfileImageThumbnail } from "../../hooks/useApiUser";
import { useState, useRef, useEffect } from "react";
import Cropper from "react-easy-crop";
import getCroppedImg from "../../services/cropImage";
import LoadingSpinner from "../LoadingSpinner/LoadingSpinner";
import styles from "./ImageUploader.module.css";

interface ImageUploaderProps {
  onClose: () => void;
  profileImageOrigin?: profileImageOrigin;
}
export default function ImageUploader({
  onClose,
  profileImageOrigin
}: ImageUploaderProps) {
  const [crop, setCrop] = useState<Point>({ x: 0, y: 0 });
  const [croppedAreaPercent, setCroppedAreaPercent] = useState<Area | null>(null);
  const [croppedAreaPixels, setCroppedAreaPixels] = useState<Area | null>(null);
  const [imageOrigin, setImageOrigin] = useState<Blob | null>(null);
  const [imageURL, setImageURL] = useState<string | null>(null);
  const [initialCroppedAreaPercent, setInitialCroppedAreaPercent] = useState<Area | null>(null);
  const [uploading, setUploading] = useState(false);
  const [rotation, setRotation] = useState(0);
  const [zoom, onZoomChange] = useState(1);

  const fileInput = useRef<HTMLInputElement>(null);
  const initialX = useRef(0);
  const initialY = useRef(0);
  const isRotatingRef = useRef(false);
  const isTouchDevice = useRef("ontouchstart" in window || navigator.maxTouchPoints > 0);

  const { mutate: saveOriginProfileImage } = useSaveProfileImageOrigin();
  const { mutate: uploadThumbnail } = useUserProfileImageThumbnail();

  useEffect(() => {
    if (!profileImageOrigin?.image) return;
    setImageOrigin(profileImageOrigin.image);
    const objectUrl = URL.createObjectURL(profileImageOrigin.image);
    setImageURL(objectUrl);
    return () => {
      URL.revokeObjectURL(objectUrl);
    };
  }, [profileImageOrigin?.image]);

  useEffect(() => {
    if (!profileImageOrigin?.info) return;
    setInitialCroppedAreaPercent(profileImageOrigin.info.area);
    setRotation(profileImageOrigin.info.rotation);
  }, [profileImageOrigin?.info]);

  /**
   * Normaliserar rotationsvärdet till 0-359.
   * @param rotation
   * @returns number
   */
  const normalizeRotation = (rotation: number): number => {
    if (!isFinite(rotation)) return 0;
    const normalized = ((rotation % 360) + 360) % 360;
    return Math.round(normalized);
  }

  /**
   * Rotationsvärdet var helt fel på iOS, så jag skapade en funktion som normaliserar det.
   * @param value
   */
  const safeSetRotation = (value: number) => {
    const safe = normalizeRotation(value);
    setRotation(safe);
  };

  const handleFileChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    if (event.target.files && event.target.files.length > 0) {
      const blob = new Blob([event.target.files[0]], { type: event.target.files[0].type });
      const newObjectURL = URL.createObjectURL(blob);

      setImageOrigin(blob);
      setInitialCroppedAreaPercent({ x: 0, y: 0, width: 100, height: 100 });
      setCrop({ x: 0, y: 0 });
      onZoomChange(1);
      setRotation(0);
      setImageURL(newObjectURL);
      setTimeout(() => URL.revokeObjectURL(newObjectURL), 1000);
    }
  };

  const handleSaveImage = async () => {
    if (!croppedAreaPixels || !croppedAreaPercent || !imageOrigin) return;
    setUploading(true);
    const croppedBlob = await getCroppedImg(imageOrigin, croppedAreaPixels, rotation);
    if (!croppedBlob) {
      console.error("Misslyckades med att beskära bilden");
      setUploading(false);
      return;
    }
    saveOriginProfileImage(
      {
        info: {
          area: croppedAreaPercent,
          rotation: Math.round(rotation),
        },
        image: (profileImageOrigin?.image === imageOrigin) ? undefined : imageOrigin,
      },
      {
        onSuccess: () => {
          const blobUrl = URL.createObjectURL(croppedBlob);

          uploadThumbnail(croppedBlob, {
            onSuccess: () => {
              setUploading(false);
              onClose();
            },
            onError: (err) => {
              setUploading(false);
              console.error("Misslyckades spara profilbild:", err.message);
            },
          });
          setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
        },
        onError: (err) => {
          setUploading(false);
          console.log("Profilbilsinfo fel:", err.message);
        },
      }

    );
  };

  const onCropComplete = async (croppedPercentage: Area, croppedPixels: Area) => {
    setCroppedAreaPercent(croppedPercentage);
    setCroppedAreaPixels(croppedPixels);
  };

  const handleMouseMove = (e: MouseEvent) => {
    if (isRotatingRef.current) {
      const x = e.clientX;
      const y = e.clientY;
      const yDiff = initialY.current - y;
      const xDiff = x - initialX.current;
      const totalDiff = (yDiff + xDiff) % 360;
      safeSetRotation(rotation + totalDiff);
    }
  }

  const startRotation = (e: React.MouseEvent) => {
    initialX.current = e.clientX;
    initialY.current = e.clientY;
    isRotatingRef.current = true;
    window.addEventListener("mousemove", handleMouseMove);
    window.addEventListener("mouseup", stopRotation);
  }

  const stopRotation = () => {
    isRotatingRef.current = false;
    window.removeEventListener("mousemove", handleMouseMove);
    window.removeEventListener("mouseup", stopRotation);
  }

  const handleClose = () => {
    if (imageURL) {
      URL.revokeObjectURL(imageURL);
    }
    onClose();
  }

  const CONTAINER_HEIGHT = 300;

  return (
    <>
      <div className={styles.editor}>
        <div className={styles.cropContainer}>
          {uploading && (
            <div className={styles.uploadSpinner}>
              <LoadingSpinner text="Uploading" />
            </div>
          )}
          {imageURL ? (
            <Cropper
              image={imageURL}
              crop={crop}
              zoom={zoom}
              cropSize={{ width: CONTAINER_HEIGHT, height: CONTAINER_HEIGHT }}
              aspect={1}
              cropShape="round"
              showGrid={false}
              maxZoom={10}

              initialCroppedAreaPercentages={initialCroppedAreaPercent ?? { x: 0, y: 0, width: 100, height: 100 }}
              rotation={rotation}
              style={{

                containerStyle: {
                  position: "relative",
                  width: CONTAINER_HEIGHT + "px",
                  height: CONTAINER_HEIGHT + "px",
                  backdropFilter: "contrast(0.1)",
                  overflow: "hidden",
                  borderRadius: "50%",
                },
              }}
              onCropChange={setCrop}
              onCropComplete={onCropComplete}
              onZoomChange={onZoomChange}
              onRotationChange={safeSetRotation}

            />
          ) : (
            <LoadingSpinner />
          )}
        </div>

        <div className={styles.controls}>

          {!isTouchDevice.current &&
            <span className={styles.symbolButton}>
              <FaArrowsRotate
                className={styles.saveSymbol}
                onMouseDown={startRotation}
                onMouseUpCapture={stopRotation}
              />
            </span>
          }
          <button onClick={handleSaveImage} className={styles.symbolButton}>
            <FaSave className={styles.saveSymbol} />
          </button>
          <button className={styles.symbolButton}>
            <FaImage className={styles.saveSymbol} onClick={() => fileInput.current?.click()} />
          </button>
          <input
            type="file"
            accept="image/*"
            ref={fileInput}
            onChange={handleFileChange}
            style={{ display: "none" }}
          />

          <button className={styles.symbolButton}>
            <IoMdCloseCircleOutline className={styles.saveSymbol} onClick={handleClose} />
          </button>
        </div>

      </div>
    </>
  );
}

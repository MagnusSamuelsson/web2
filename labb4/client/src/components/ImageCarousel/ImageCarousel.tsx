import { useState, useRef } from "react";
import styles from "./ImageCarousel.module.css";

interface ImageCarouselProps {
    images: string[];
    postId: number;
}

export default function ImageCarousel({ images, postId }: ImageCarouselProps) {
    const [current, setCurrent] = useState(0);
    const touchStartX = useRef<number | null>(null);

    const handleTouchStart = (e: React.TouchEvent) => {
        touchStartX.current = e.touches[0].clientX;
    };

    const handleTouchEnd = (e: React.TouchEvent) => {
        if (touchStartX.current === null) return;
        const touchEndX = e.changedTouches[0].clientX;
        const diff = touchStartX.current - touchEndX;

        if (diff > 50) nextImage();
        else if (diff < -50) prevImage();
        touchStartX.current = null;
    };

    const prevImage = () => {
        setCurrent((prev) => (prev === 0 ? images.length - 1 : prev - 1));
    };

    const nextImage = () => {
        setCurrent((prev) => (prev === images.length - 1 ? 0 : prev + 1));
    };

    if (!images || images.length === 0) return null;

    return (
        <div className={styles.carousel}>
            <div
                className={styles.imageWrapper}
                onTouchStart={handleTouchStart}
                onTouchEnd={handleTouchEnd}
            >
                <img
                    src={
                        images[current].startsWith("blob:")
                            ? images[current]
                            : `/api/postimage/${images[current]}`
                    }
                    alt={`post-${postId}-image-${current}`}
                    className={styles.image}
                />

                {images.length > 1 && (
                    <>
                        <button onClick={prevImage} className={styles.navButton + " " + styles.left}>
                            ‹
                        </button>
                        <button onClick={nextImage} className={styles.navButton + " " + styles.right}>
                            ›
                        </button>
                    </>
                )}
                {images.length > 1 && (
                <div className={styles.dots}>
                    {images.map((_, index) => (
                        <span
                            key={index}
                            className={`${styles.dot} ${index === current ? styles.activeDot : ""}`}
                            onClick={() => setCurrent(index)}
                        />
                    ))}
                </div>
            )}
            </div>


        </div>
    );
}

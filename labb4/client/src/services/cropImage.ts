export const createImage = (url: string): Promise<HTMLImageElement> => {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.crossOrigin = "anonymous"; // Undviker CORS-problem
    image.src = url;
    image.onload = () => resolve(image);
    image.onerror = (error) => reject(error);
  });
};

export function getRadianAngle(degreeValue: number): number {
  return (degreeValue * Math.PI) / 180;
}

/**
 * Returns the new bounding area of a rotated rectangle.
 */
export function rotateSize(width: number, height: number, rotation: number) {
  const rotRad = getRadianAngle(rotation);

  return {
    width: Math.abs(Math.cos(rotRad) * width) + Math.abs(Math.sin(rotRad) * height),
    height: Math.abs(Math.sin(rotRad) * width) + Math.abs(Math.cos(rotRad) * height),
  };
}

function normalizeRotation(rotation: number): number {
  if (isNaN(rotation) || typeof rotation !== "number") return 0;
  return ((rotation % 360) + 360) % 360; // alltid 0–359
}

function dataURLToBlob(dataURL: string): Blob {
  const byteString = atob(dataURL.split(',')[1]);
  const mimeString = dataURL.split(',')[0].split(':')[1].split(';')[0];

  const ab = new ArrayBuffer(byteString.length);
  const ia = new Uint8Array(ab);
  for (let i = 0; i < byteString.length; i++) {
    ia[i] = byteString.charCodeAt(i);
  }

  return new Blob([ab], { type: mimeString });
}
/**
 * Crops an image and returns a Blob URL (JPEG or WebP)
 */
export default async function getCroppedImg(
  imageBlob: Blob,
  pixelCrop: { x: number; y: number; width: number; height: number },
  rotation: number = 0,
  flip: { horizontal: boolean; vertical: boolean } = { horizontal: false, vertical: false },
): Promise<Blob | null> {
  try {
    const imageUrl = URL.createObjectURL(imageBlob);
    const image = await createImage(imageUrl);
    URL.revokeObjectURL(imageUrl);
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");

    if (!ctx) return null;

    const safeRotation = normalizeRotation(rotation);
    const rotRad = getRadianAngle(safeRotation);
    const { width: bBoxWidth, height: bBoxHeight } = rotateSize(image.width, image.height, safeRotation);

    canvas.width = bBoxWidth;
    canvas.height = bBoxHeight;

    ctx.translate(bBoxWidth / 2, bBoxHeight / 2);
    ctx.rotate(rotRad);
    ctx.scale(flip.horizontal ? -1 : 1, flip.vertical ? -1 : 1);
    ctx.translate(-image.width / 2, -image.height / 2);
    ctx.drawImage(image, 0, 0);

    const croppedCanvas = document.createElement("canvas");
    const croppedCtx = croppedCanvas.getContext("2d");

    if (!croppedCtx) return null;

    croppedCanvas.width = pixelCrop.width;
    croppedCanvas.height = pixelCrop.height;

    croppedCtx.drawImage(
      canvas,
      pixelCrop.x,
      pixelCrop.y,
      pixelCrop.width,
      pixelCrop.height,
      0,
      0,
      pixelCrop.width,
      pixelCrop.height
    );

    // Workaround: konvertera till dataURL manuellt på Safari
    return new Promise<Blob | null>((resolve) => {
      croppedCanvas.toBlob(
        (file) => {
          // iOS Safari struntar ibland i MIME-type – kontrollera typ
          if (file && file.type === "image/webp") {
            resolve(file);
          } else {
            const dataUrl = croppedCanvas.toDataURL("image/jpeg", 0.9);
            const webpBlob = dataURLToBlob(dataUrl);
            resolve(webpBlob);
          }
        },
        "image/webp",
        0.9
      );
    });
  } catch (error) {
    console.error("Error cropping image:", error);
    return null;
  }
}

export const blobToBase64 = (blob: Blob): Promise<string> => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.readAsDataURL(blob);
    reader.onloadend = () => resolve(reader.result as string);
    reader.onerror = reject;
  });
};

/**
 * Convert BASE64 to BLOB
 * @param base64Image Pass Base64 image data to convert into the BLOB
 */
export const convertBase64ToBlob = (b64Data : string, sliceSize=256): Blob => {
  const parts = b64Data.split(';base64,')
  const byteCharacters = atob(parts[1]);
  const byteArrays = [];
  const imageType = parts[0].split(':')[1];
  for (let offset = 0; offset < byteCharacters.length; offset += sliceSize) {
    const slice = byteCharacters.slice(offset, offset + sliceSize);

    const byteNumbers = new Array(slice.length);
    for (let i = 0; i < slice.length; i++) {
      byteNumbers[i] = slice.charCodeAt(i);
    }

    const byteArray = new Uint8Array(byteNumbers);
    byteArrays.push(byteArray);
  }

  const blob = new Blob(byteArrays, {type: imageType});
  return blob;
}

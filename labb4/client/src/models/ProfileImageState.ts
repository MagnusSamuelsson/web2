import { Area } from 'react-easy-crop';

export default interface ProfileImageState {
    imagePreviewUrl: string | null;
    originalImageBlob: Blob | null;
    originalImageArea: Area | undefined;
    originalImageRotation: number;
    originalImageExists: boolean;
  }
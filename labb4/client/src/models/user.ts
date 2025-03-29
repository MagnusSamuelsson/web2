import type { Area } from "react-easy-crop";

export type User = {
  id?: number | null;
  username: string;
  profile_image: string;
  description: string;
}

export type UserProfile = {
  id?: number | null;
  username: string;
  profile_image: string;
  description: string;
  number_of_posts: number;
  number_of_followers: number;
  number_of_following: number;
  is_following_current_user?: boolean;
  is_followed_by_current_user?: boolean;
}
export type ProfileImageInfo = {
  area: Area;
  rotation: number;
}

export type profileImageOrigin = {
  info: ProfileImageInfo;
  image?: Blob;
}

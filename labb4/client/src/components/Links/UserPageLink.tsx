import { Link } from "react-router-dom";
import { User } from "../../models/user";

const UserPageLink = ({ user }: { user: User }) => {
    return (
        <Link
            to={`/User/${user.id}`}
            onClick={() => { window.scrollTo(0, 0) }}
        >
            {user.username}
        </Link>
    );
}
export default UserPageLink;
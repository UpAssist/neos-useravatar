import React, {useEffect, useState} from 'react';
import style from './style.module.css';

/**
 * Wraps the user menu of the Neos UI without replacing it: logout, user settings and
 * "restore impersonated user" stay the Neos component. When the photo loads, CSS puts it on
 * the first element of the menu button (the initials in Neos 9, the user icon in Neos 8).
 * When it does not (404: no photo), nothing changes.
 */
export default function withAvatar(UserDropDown, uri) {
    const UserDropDownWithAvatar = props => {
        const [photo, setPhoto] = useState(null);

        useEffect(() => {
            const image = new window.Image();
            image.onload = () => setPhoto(image.src);
            image.src = uri;
            return () => {
                image.onload = null;
            };
        }, []);

        return (
            <div
                className={photo ? style.withPhoto : style.wrapper}
                style={photo ? {'--upassist-useravatar-image': `url("${photo}")`} : undefined}
            >
                <UserDropDown {...props}/>
            </div>
        );
    };
    UserDropDownWithAvatar.displayName = 'UserDropDownWithAvatar';

    return UserDropDownWithAvatar;
}

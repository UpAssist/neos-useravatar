import manifest from '@neos-project/neos-ui-extensibility';
import withAvatar from './withAvatar';

// Where the Neos UI renders the user menu: the drawer in Neos 9, the top bar in Neos 8.
const USER_MENU_CONTAINERS = ['Drawer/Bottom/UserDropDown', 'PrimaryToolbar/Right/UserDropDown'];

manifest('UpAssist.Neos.UserAvatar:Plugin', {}, (globalRegistry, {frontendConfiguration}) => {
    const uri = frontendConfiguration?.['UpAssist.Neos.UserAvatar']?.uri || '/neos/user-avatar';
    const containerRegistry = globalRegistry.get('containers');

    USER_MENU_CONTAINERS.forEach(key => {
        const UserDropDown = containerRegistry.get(key);
        if (UserDropDown) {
            containerRegistry.set(key, withAvatar(UserDropDown, uri));
        }
    });
});

import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <div className="flex items-center">
            <AppLogoIcon className="size-5 fill-current text-black dark:text-white" />
            <span className="ml-2 truncate leading-tight text-sm font-semibold">Intelli-Lock</span>
        </div>
    );
}

/**
 * Main Layout Component
 *
 * Provides the structural shell for the entire admin application.
 * Handles the main container, navigation placement, and global spacing.
 * Layout styles are included in design-system-v2.css
 */

export default function Layout({
	children,
	navigation,
	isFullWidth = false,
}) {
	return (
		<div className="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
			<style>{`
				#wpfooter { display: none !important; }
				#wpbody-content { padding-bottom: 0 !important; }
			`}</style>
			<header className={`sticky top-8 z-30 bg-slate-50/80 px-4 backdrop-blur dark:bg-slate-950/80 sm:px-6 ${isFullWidth ? 'py-2' : 'py-4'}`}>
				{navigation}
			</header>
			<main className={`px-4 sm:px-6 ${isFullWidth ? 'pb-0' : 'pb-10'}`}>
				<div
					className={`${isFullWidth ? 'max-w-none' : 'max-w-7xl'} mx-auto w-full`}
				>
					{children}
				</div>
			</main>
		</div>
	);
}

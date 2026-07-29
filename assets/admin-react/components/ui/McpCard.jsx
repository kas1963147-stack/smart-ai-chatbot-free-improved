/**
 * McpCard Component
 * 
 * Reusable MCP card component for consistent display across:
 * - Main MCPs page (McpPage.jsx)
 * - Agent Editor MCPs tab (McpManager.jsx)
 * 
 * Uses Lucide React SVG icons for consistent styling.
 * Styled with Tailwind CSS following Metronic v9 patterns.
 */
import PropTypes from 'prop-types';
import {
    Mail,
    MessageSquare,
    Calendar,
    Globe,
    Cloud,
    Database,
    Code,
    Link,
    Check,
    Settings,
    Plug,
    FileText,
    Monitor,
    BarChart3,
    Search,
    Layers,
    Zap,
    Network,
    GitBranch,
    Trello,
    CheckSquare,
    Users,
    FolderOpen,
    Clock,
    Image,
    MapPin,
    AlertTriangle,
    CheckCircle2,
    KeyRound,
} from 'lucide-react';

// Lucide React icon mapping for MCP services
const MCP_ICONS = {
    // Communication
    email: Mail,
    mail: Mail,
    gmail: Mail,
    'admin-comments': MessageSquare,
    slack: MessageSquare,
    comment: MessageSquare,

    // Productivity
    'calendar-alt': Calendar,
    calendar: Calendar,
    'google-calendar': Calendar,
    notion: Layers,
    trello: Trello,
    jira: CheckSquare,
    linear: GitBranch,
    todoist: CheckSquare,

    // Cloud & Storage
    cloud: Cloud,
    'cloud-saved': Cloud,
    database: Database,
    'database-view': Database,

    // Development
    code: Code,
    'media-code': Code,
    'rest-api': Code,
    randomize: Code,
    github: GitBranch,

    // Network & Web
    globe: Globe,
    'admin-site': Globe,
    networking: Network,
    link: Link,

    // Utilities
    clock: Clock,
    search: Search,
    visibility: Search,
    cog: Settings,
    lightbulb: Zap,
    settings: Settings,

    // Plugins & Extensions
    plugins: Plug,
    'plugins-checked': Plug,
    'admin-multisite': Plug,

    // Media & Files
    media: Image,
    page: FileText,
    book: FileText,
    'text-page': FileText,

    // Display
    desktop: Monitor,
    'chart-line': BarChart3,

    // Other
    location: MapPin,
    users: Users,
    folder: FolderOpen,
    check: Check,
    'yes-alt': Check,
    'arrow-up-alt': Cloud,
};

// Get icon component by name
const getIconComponent = (iconName) => {
    return MCP_ICONS[iconName] || Globe;
};

/**
 * Determine the configuration status of an MCP
 * Returns: 'configured' | 'not_required' | 'not_configured'
 */
const getConfigStatus = (mcp) => {
    const requiresApiKey = mcp.api_key_field !== null && mcp.api_key_field !== undefined && mcp.api_key_field !== '';
    const hasApiKey = !!(mcp.default_config?.api_key);

    if (!requiresApiKey) {
        return 'not_required'; // No API key needed — ready to use
    }
    if (hasApiKey) {
        return 'configured'; // Has API key — ready to use
    }
    return 'not_configured'; // Needs API key but doesn't have one
};

export default function McpCard({
    mcp,
    enabled = false,
    onToggle,
    onConfigure,
    showConfigButton = false,
    showUrl = true,
    showStatus = false,
    className = '',
}) {
    const IconComponent = getIconComponent(mcp.icon);
    const configStatus = getConfigStatus(mcp);

    const handleClick = () => {
        if (onToggle) {
            onToggle(mcp.id);
        }
    };

    const handleCheckboxClick = (e) => {
        e.stopPropagation();
        if (onToggle) {
            onToggle(mcp.id);
        }
    };

    const handleConfigureClick = (e) => {
        e.stopPropagation();
        if (onConfigure) {
            onConfigure(mcp);
        }
    };

    // Border color based on config status when showStatus is on
    const getBorderClass = () => {
        if (enabled && showStatus) {
            if (configStatus === 'not_configured') {
                return 'bg-white border-amber-200 hover:border-amber-300 shadow-sm';
            }
            return 'bg-white border-primary/30 hover:border-primary/50 shadow-sm';
        }
        if (enabled) {
            return 'bg-white border-primary/30 hover:border-primary/50 shadow-sm';
        }
        return 'bg-white border-gray-200 hover:border-primary/30 shadow-sm';
    };

    return (
        <div
            className={`
				relative flex flex-col p-5 pr-12 rounded-xl border cursor-pointer
				transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5
				${getBorderClass()}
				${className}
			`}
            onClick={handleClick}
        >
            {/* Checkbox at top right */}
            {onToggle && (
                <input
                    type="checkbox"
                    checked={enabled}
                    onChange={handleCheckboxClick}
                    onClick={handleCheckboxClick}
                    className="absolute top-4 right-4 w-4 h-4 rounded border-border text-primary focus:ring-primary/20 cursor-pointer"
                />
            )}

            {/* Header with icon and title */}
            <div className="flex items-start gap-3 mb-3">
                {/* Icon Container */}
                <div className={`flex items-center justify-center w-12 h-12 rounded-xl flex-shrink-0
                    ${showStatus && configStatus === 'not_configured'
                        ? 'bg-amber-50 text-amber-600'
                        : showStatus && (configStatus === 'configured' || configStatus === 'not_required')
                            ? 'bg-primary/10 text-primary'
                            : 'bg-primary/10 text-primary'
                    }
                `}>
                    <IconComponent size={24} />
                </div>

                {/* Title and Type */}
                <div className="flex-1 min-w-0">
                    <h3 className="text-[15px] font-semibold text-foreground mb-1">
                        {mcp.name}
                    </h3>
                    <span className={`
						inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
						${mcp.type === 'sse'
                            ? 'bg-primary/10 text-primary'
                            : 'bg-accent text-accent-foreground'
                        }
					`}>
                        {mcp.type === 'sse' ? 'Remote (HTTP)' : 'Local (Stdio)'}
                    </span>
                </div>
            </div>

            {/* Description */}
            <p className="text-[13px] text-muted-foreground leading-relaxed mb-3 flex-1">
                {mcp.description}
            </p>

            {/* URL (if available and showUrl is true) */}
            {showUrl && (mcp.url || mcp.default_config?.url) && (
                <div className="text-[11px] font-mono text-muted-foreground bg-muted px-2 py-1.5 rounded-lg truncate mb-3">
                    {mcp.url || mcp.default_config?.url}
                </div>
            )}

            {/* Configuration Status */}
            {showStatus && (
                <div className={`
                    flex items-center gap-1.5 border-t pt-3 mt-auto text-xs font-medium
                    ${configStatus === 'configured' || configStatus === 'not_required'
                        ? 'border-emerald-100 text-emerald-700'
                        : 'border-amber-100 text-amber-700'
                    }
                `}>
                    {configStatus === 'configured' ? (
                        <>
                            <CheckCircle2 size={14} className="text-emerald-500 flex-shrink-0" />
                            <span>API key configured</span>
                        </>
                    ) : configStatus === 'not_required' ? (
                        <>
                            <CheckCircle2 size={14} className="text-emerald-500 flex-shrink-0" />
                            <span>Ready</span>
                        </>
                    ) : (
                        <>
                            <AlertTriangle size={14} className="text-amber-500 flex-shrink-0" />
                            <span>Needs API key</span>
                        </>
                    )}
                </div>
            )}

            {/* Configure Button (for McpPage) */}
            {showConfigButton && onConfigure && (
                <button
                    onClick={handleConfigureClick}
                    className={`
						w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg border transition-colors
						${mcp.default_config?.api_key
                            ? 'bg-primary/10 border-primary/30 text-primary hover:bg-primary/15'
                            : 'bg-muted border-border text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                        }
					`}
                >
                    {mcp.default_config?.api_key ? (
                        <>
                            <Check size={16} />
                            API Key Configured
                        </>
                    ) : (
                        <>
                            <KeyRound size={16} />
                            Configure API Key
                        </>
                    )}
                </button>
            )}
        </div>
    );
}

// Export the icon getter and config status helper for use in other components
export { getIconComponent, MCP_ICONS, getConfigStatus };

McpCard.propTypes = {
    mcp: PropTypes.shape({
        id: PropTypes.string.isRequired,
        name: PropTypes.string.isRequired,
        description: PropTypes.string,
        type: PropTypes.oneOf(['sse', 'stdio']),
        icon: PropTypes.string,
        url: PropTypes.string,
        default_config: PropTypes.object,
        api_key_field: PropTypes.string,
    }).isRequired,
    enabled: PropTypes.bool,
    onToggle: PropTypes.func,
    onConfigure: PropTypes.func,
    showConfigButton: PropTypes.bool,
    showUrl: PropTypes.bool,
    showStatus: PropTypes.bool,
    className: PropTypes.string,
};

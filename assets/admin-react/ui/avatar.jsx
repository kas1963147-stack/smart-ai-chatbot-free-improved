/**
 * Avatar Component - Metronic v9 Style
 * Simplified JSX version
 */
import * as React from 'react';
import * as AvatarPrimitive from '@radix-ui/react-avatar';
import { cva } from 'class-variance-authority';
import { cn } from './utils';

const avatarStatusVariants = cva(
    'flex items-center rounded-full size-2 border-2 border-background',
    {
        variants: {
            variant: {
                online: 'bg-green-600',
                offline: 'bg-zinc-400 dark:bg-zinc-500',
                busy: 'bg-yellow-600',
                away: 'bg-blue-600',
            },
        },
        defaultVariants: {
            variant: 'online',
        },
    }
);

const Avatar = React.forwardRef(({ className, ...props }, ref) => (
    <AvatarPrimitive.Root
        ref={ref}
        className={cn('relative flex h-10 w-10 shrink-0 overflow-hidden rounded-full', className)}
        {...props}
    />
));
Avatar.displayName = AvatarPrimitive.Root.displayName;

const AvatarImage = React.forwardRef(({ className, ...props }, ref) => (
    <AvatarPrimitive.Image
        ref={ref}
        className={cn('aspect-square h-full w-full', className)}
        {...props}
    />
));
AvatarImage.displayName = AvatarPrimitive.Image.displayName;

const AvatarFallback = React.forwardRef(({ className, ...props }, ref) => (
    <AvatarPrimitive.Fallback
        ref={ref}
        className={cn(
            'flex h-full w-full items-center justify-center rounded-full bg-muted text-xs',
            className
        )}
        {...props}
    />
));
AvatarFallback.displayName = AvatarPrimitive.Fallback.displayName;

function AvatarIndicator({ className, ...props }) {
    return (
        <div
            className={cn('absolute flex size-6 items-center justify-center', className)}
            {...props}
        />
    );
}

function AvatarStatus({ className, variant, ...props }) {
    return (
        <div
            className={cn(avatarStatusVariants({ variant }), className)}
            {...props}
        />
    );
}

export { Avatar, AvatarImage, AvatarFallback, AvatarIndicator, AvatarStatus, avatarStatusVariants };

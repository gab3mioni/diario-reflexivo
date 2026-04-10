import { useEffect } from 'react';
import echo from '@/lib/echo';

type ChannelType = 'private' | 'public';

/**
 * Subscribes to a (private) channel for the lifetime of the component.
 * KISS: takes a single event/callback. For multiple events, call the hook
 * multiple times — clearer than registering a map.
 */
export function useEcho(
    channel: string,
    event: string,
    callback: (payload: unknown) => void,
    type: ChannelType = 'private',
): void {
    useEffect(() => {
        if (!channel) {
            return;
        }

        const subscription =
            type === 'private' ? echo.private(channel) : echo.channel(channel);

        subscription.listen(event, callback);

        return () => {
            subscription.stopListening(event);
            if (type === 'private') {
                echo.leave(`private-${channel}`);
            } else {
                echo.leave(channel);
            }
        };
    }, [channel, event, type, callback]);
}

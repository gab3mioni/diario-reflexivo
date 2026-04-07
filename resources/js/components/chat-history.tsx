import type { ChatMessage } from '@/types/models';
import { Bot, User } from 'lucide-react';

interface ChatHistoryProps {
    messages: ChatMessage[];
    showTimestamps?: boolean;
}

function formatTimestamp(dateStr: string): string {
    const date = new Date(dateStr);
    return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

export function ChatHistory({ messages, showTimestamps = true }: ChatHistoryProps) {
    if (messages.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                <p className="text-sm">Nenhuma mensagem registrada.</p>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-3 py-2">
            {messages.map((message) => (
                <div
                    key={message.id}
                    className={`flex gap-2.5 ${message.role === 'student' ? 'flex-row-reverse' : 'flex-row'}`}
                >
                    <div
                        className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full ${message.role === 'bot'
                                ? 'bg-primary/10 text-primary'
                                : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                            }`}
                    >
                        {message.role === 'bot' ? (
                            <Bot className="h-3.5 w-3.5" />
                        ) : (
                            <User className="h-3.5 w-3.5" />
                        )}
                    </div>

                    <div
                        className={`max-w-[75%] rounded-2xl px-3.5 py-2.5 ${message.role === 'bot'
                                ? 'bg-muted text-foreground rounded-tl-sm'
                                : 'bg-primary text-primary-foreground rounded-tr-sm'
                            }`}
                    >
                        <p className="text-sm whitespace-pre-wrap leading-relaxed">{message.content}</p>
                        {showTimestamps && (
                            <p
                                className={`mt-1 text-[10px] ${message.role === 'bot'
                                        ? 'text-muted-foreground/70'
                                        : 'text-primary-foreground/70'
                                    }`}
                            >
                                {formatTimestamp(message.created_at)}
                            </p>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}

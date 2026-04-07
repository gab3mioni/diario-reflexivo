import type { ChatMessage } from '@/types/models';
import { router } from '@inertiajs/react';
import { Bot, Send, User } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

interface ChatDiaryProps {
    lessonId: number;
    chatMessages: ChatMessage[];
    currentNodeId: string | null;
    totalQuestions: number;
    isCompleted: boolean;
    draft: string;
}

function formatTime(dateStr: string): string {
    const date = new Date(dateStr);
    return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function typingDelay(message: string): number {
    const base = 800;
    const perChar = 15;
    return Math.min(base + message.length * perChar, 3000);
}

export function ChatDiary({ lessonId, chatMessages, currentNodeId, totalQuestions, isCompleted, draft }: ChatDiaryProps) {
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLTextAreaElement>(null);
    const draftTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const startedRef = useRef(false);

    const [inputContent, setInputContent] = useState(draft ?? '');
    const [isProcessing, setIsProcessing] = useState(false);
    const [visibleCount, setVisibleCount] = useState(0);
    const [isTyping, setIsTyping] = useState(false);

    const answeredCount = chatMessages.filter((m) => m.role === 'student').length;

    const scrollToBottom = useCallback(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, []);

    // Gradually reveal bot messages with typing delay
    useEffect(() => {
        if (visibleCount >= chatMessages.length) {
            setIsTyping(false);
            return;
        }

        const nextMessage = chatMessages[visibleCount];

        if (nextMessage.role === 'student') {
            setVisibleCount((c) => c + 1);
            return;
        }

        // Bot message — show typing indicator, then reveal
        setIsTyping(true);
        const timeout = setTimeout(() => {
            setIsTyping(false);
            setVisibleCount((c) => c + 1);
        }, typingDelay(nextMessage.content));

        return () => clearTimeout(timeout);
    }, [visibleCount, chatMessages.length, chatMessages]);

    // When new messages arrive from server, keep already-visible ones and queue new ones
    const prevCountRef = useRef(chatMessages.length);
    useEffect(() => {
        if (chatMessages.length > prevCountRef.current) {
            // New messages arrived — visibleCount stays, the effect above will animate them
        } else if (chatMessages.length > 0 && visibleCount === 0) {
            // First load with existing messages — show them all immediately
            setVisibleCount(chatMessages.length);
        }
        prevCountRef.current = chatMessages.length;
    }, [chatMessages.length]);

    useEffect(() => {
        scrollToBottom();
    }, [visibleCount, isTyping, scrollToBottom]);

    useEffect(() => {
        if (chatMessages.length === 0 && !isCompleted && !startedRef.current) {
            startedRef.current = true;
            router.post(route('lessons.chat.start', lessonId), {}, {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setIsProcessing(true),
                onFinish: () => setIsProcessing(false),
            });
        }
    }, []);

    useEffect(() => {
        if (draftTimerRef.current) clearTimeout(draftTimerRef.current);

        if (inputContent.trim()) {
            draftTimerRef.current = setTimeout(() => {
                router.put(route('lessons.chat.draft', lessonId), {
                    content: inputContent,
                }, { preserveState: true, preserveScroll: true });
            }, 3000);
        }

        return () => {
            if (draftTimerRef.current) clearTimeout(draftTimerRef.current);
        };
    }, [inputContent]);

    const handleSend = () => {
        if (!inputContent.trim() || isProcessing || isCompleted || !currentNodeId) return;

        const content = inputContent.trim();
        setInputContent('');

        if (inputRef.current) {
            inputRef.current.style.height = 'auto';
        }

        router.post(route('lessons.chat.message', lessonId), {
            content,
            node_id: currentNodeId,
        }, {
            preserveState: true,
            preserveScroll: true,
            onStart: () => setIsProcessing(true),
            onFinish: () => {
                setIsProcessing(false);
                inputRef.current?.focus();
            },
            onError: () => setInputContent(content),
        });
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    };

    const allRevealed = visibleCount >= chatMessages.length && !isTyping;
    const canSend = inputContent.trim().length > 0 && !isProcessing && !isCompleted && allRevealed;

    const visibleMessages = useMemo(
        () => chatMessages.slice(0, visibleCount),
        [chatMessages, visibleCount],
    );

    const questionProgress = totalQuestions > 0
        ? `Pergunta ${Math.min(answeredCount + 1, totalQuestions)} de ${totalQuestions}`
        : '';

    return (
        <div className="flex flex-col h-[600px] rounded-xl border border-border/60 bg-card overflow-hidden">
            <div className="flex items-center gap-2 border-b px-4 py-3 bg-muted/30">
                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <Bot className="h-4 w-4" />
                </div>
                <div>
                    <p className="text-sm font-medium">Diário Reflexivo</p>
                    <p className="text-xs text-muted-foreground">
                        {isCompleted
                            ? 'Conversa finalizada'
                            : (isProcessing || isTyping)
                                ? 'Digitando...'
                                : questionProgress}
                    </p>
                </div>
            </div>

            <div className="flex-1 overflow-y-auto px-4 py-4">
                <div className="flex flex-col gap-3">
                    {visibleMessages.map((message) => (
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
                                <p
                                    className={`mt-1 text-[10px] ${message.role === 'bot'
                                        ? 'text-muted-foreground/70'
                                        : 'text-primary-foreground/70'
                                        }`}
                                >
                                    {formatTime(message.created_at)}
                                </p>
                            </div>
                        </div>
                    ))}

                    {(isProcessing || isTyping) && (
                        <div className="flex gap-2.5">
                            <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <Bot className="h-3.5 w-3.5" />
                            </div>
                            <div className="bg-muted rounded-2xl rounded-tl-sm px-4 py-3">
                                <div className="flex gap-1">
                                    <span className="h-2 w-2 rounded-full bg-muted-foreground/40 animate-bounce [animation-delay:0ms]" />
                                    <span className="h-2 w-2 rounded-full bg-muted-foreground/40 animate-bounce [animation-delay:150ms]" />
                                    <span className="h-2 w-2 rounded-full bg-muted-foreground/40 animate-bounce [animation-delay:300ms]" />
                                </div>
                            </div>
                        </div>
                    )}

                    <div ref={messagesEndRef} />
                </div>
            </div>

            {!isCompleted ? (
                <div className="border-t bg-background px-4 py-3">
                    <div className="flex items-end gap-2">
                        <textarea
                            ref={inputRef}
                            value={inputContent}
                            onChange={(e) => setInputContent(e.target.value)}
                            onKeyDown={handleKeyDown}
                            placeholder={
                                isProcessing || isTyping
                                    ? 'Aguarde...'
                                    : 'Digite sua resposta...'
                            }
                            disabled={isProcessing || isTyping}
                            rows={1}
                            className="flex-1 resize-none rounded-xl border border-border bg-muted/30 px-4 py-2.5 text-sm placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] outline-none disabled:opacity-50 max-h-32"
                            style={{ minHeight: '42px' }}
                            onInput={(e) => {
                                const target = e.target as HTMLTextAreaElement;
                                target.style.height = 'auto';
                                target.style.height = Math.min(target.scrollHeight, 128) + 'px';
                            }}
                        />
                        <Button
                            onClick={handleSend}
                            disabled={!canSend}
                            size="icon"
                            className="h-[42px] w-[42px] rounded-xl shrink-0"
                        >
                            <Send className="h-4 w-4" />
                        </Button>
                    </div>
                    <p className="mt-1.5 text-[10px] text-muted-foreground text-center">
                        Pressione Enter para enviar · Shift+Enter para nova linha
                    </p>
                </div>
            ) : (
                <div className="border-t bg-green-50 dark:bg-green-950/20 px-4 py-3 text-center">
                    <p className="text-sm text-green-700 dark:text-green-400 font-medium">
                        Diário reflexivo enviado com sucesso
                    </p>
                </div>
            )}
        </div>
    );
}

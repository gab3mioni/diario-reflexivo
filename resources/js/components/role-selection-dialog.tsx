import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Role } from '@/types/auth';

interface RoleSelectionDialogProps {
    open: boolean;
    roles: Role[];
}

export default function RoleSelectionDialog({
    open,
    roles,
}: RoleSelectionDialogProps) {
    const handleRoleSelect = (roleSlug: string) => {
        router.post('/select-role', {
            role: roleSlug,
        });
    };

    return (
        <Dialog open={open}>
            <DialogContent className="sm:max-w-md" aria-describedby={undefined}>
                <DialogHeader>
                    <DialogTitle>Selecione seu acesso</DialogTitle>
                    <DialogDescription>
                        Você tem acesso como Aluno e Professor. Selecione qual acesso deseja utilizar nesta sessão.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-3 py-4">
                    {roles.map((role) => (
                        <Button
                            key={role.id}
                            variant="outline"
                            className="h-auto py-4 px-6 justify-start text-left"
                            onClick={() => handleRoleSelect(role.slug)}
                        >
                            <div className="flex flex-col gap-1">
                                <span className="font-semibold">{role.display_name}</span>
                                <span className="text-sm text-muted-foreground">
                                    Acessar como {role.display_name.toLowerCase()}
                                </span>
                            </div>
                        </Button>
                    ))}
                </div>

                <DialogFooter>
                    <p className="text-xs text-muted-foreground">
                        Você pode alterar o acesso a qualquer momento
                    </p>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
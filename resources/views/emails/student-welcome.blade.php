<x-mail::message>
# Olá, {{ $name }}!

Uma conta foi criada para você no **Diário Reflexivo**.

**E-mail:** {{ $email }}
**Senha temporária:** `{{ $password }}`

Por segurança, você precisará trocar a senha no primeiro acesso.

<x-mail::button :url="$loginUrl">
Acessar a plataforma
</x-mail::button>

Obrigado,<br>
Equipe Diário Reflexivo
</x-mail::message>

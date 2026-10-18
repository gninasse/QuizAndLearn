<x-mail::message>
# Nouvelle demande d'accès

**{{ $accessRequest->first_name }} {{ $accessRequest->last_name }}** a envoyé une demande d'accès.

- Téléphone : {{ $accessRequest->phone }}
@if ($accessRequest->whatsapp)
- WhatsApp : {{ $accessRequest->whatsapp }}
@endif
@if ($accessRequest->email)
- E-mail : {{ $accessRequest->email }}
@endif
@if ($accessRequest->organization)
- Organisation : {{ $accessRequest->organization }}
@endif
@if ($accessRequest->group)
- Groupe souhaité : {{ $accessRequest->group->name }}
@endif
@if ($accessRequest->message)

> {{ $accessRequest->message }}
@endif
</x-mail::message>

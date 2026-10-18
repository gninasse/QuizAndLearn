<x-mail::message>
# Nouvelle demande d'accès

**{{ \Modules\Core\Mail\NewAccessRequestMail::md($accessRequest->first_name) }} {{ \Modules\Core\Mail\NewAccessRequestMail::md($accessRequest->last_name) }}** a envoyé une demande d'accès.

- Téléphone : {{ \Modules\Core\Mail\NewAccessRequestMail::md($accessRequest->phone) }}
@if ($accessRequest->whatsapp)
- WhatsApp : {{ \Modules\Core\Mail\NewAccessRequestMail::md($accessRequest->whatsapp) }}
@endif
@if ($accessRequest->email)
- E-mail : {{ \Modules\Core\Mail\NewAccessRequestMail::md($accessRequest->email) }}
@endif
@if ($accessRequest->organization)
- Organisation : {{ \Modules\Core\Mail\NewAccessRequestMail::md($accessRequest->organization) }}
@endif
@if ($accessRequest->group)
- {{ term('Group') }} souhaité{{ term('fem_e') }} : {{ \Modules\Core\Mail\NewAccessRequestMail::md($accessRequest->group->name) }}
@endif
@if ($accessRequest->message)

> {{ \Modules\Core\Mail\NewAccessRequestMail::md($accessRequest->message) }}
@endif
</x-mail::message>

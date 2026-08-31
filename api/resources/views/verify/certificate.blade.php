<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vérification FANABE</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f4f1ea; color: #1a1a1a; }
        main { max-width: 32rem; margin: 2rem auto; padding: 1.5rem; background: #fff; border-radius: 8px; }
        h1 { font-size: 1.1rem; margin: 0 0 1rem; }
        p { margin: 0.4rem 0; }
        .muted { color: #555; font-size: 0.9rem; }
        .disclaimer { margin-top: 1.25rem; font-size: 0.8rem; color: #444; }
        label { display: block; font-size: 0.85rem; margin-top: 1rem; }
        input, button { font: inherit; padding: 0.4rem 0.6rem; }
        button { background: #1f6b4a; color: #fff; border: 0; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
<main>
    <h1>FANABE — Vérification de certificat</h1>
    @if (($result['status'] ?? 'UNKNOWN') === 'UNKNOWN')
        <p>Ce jeton n’est pas reconnu.</p>
    @else
        <p><strong>{{ $result['status'] }}</strong> · {{ $result['type_label'] ?? 'Certificat' }}</p>
        <p>{{ $result['issuer'] ?? '' }}</p>
        <p class="muted">{{ $result['year_label'] ?? '' }} · {{ $result['classroom_name'] ?? '' }}</p>
        <p>{{ $result['person'] ?? '' }}</p>
        @if (! empty($result['ended_on']))
            <p>Fin d’inscription le {{ $result['ended_on'] }}</p>
        @endif
        @if (! empty($result['exit_reason']))
            <p>Motif : {{ $result['exit_reason'] }}</p>
        @endif
        <p class="muted">Référence {{ $result['public_reference'] ?? '' }} · émis le {{ $result['issued_on'] ?? '' }}</p>
    @endif
    <form method="get">
        <label>
            Date de naissance (optionnel, pour le nom complet)
            <input type="date" name="birth_date" value="{{ $birthDate }}">
        </label>
        <p><button type="submit">Afficher le nom complet</button></p>
    </form>
    <p class="disclaimer">{{ $result['disclaimer'] ?? '' }}</p>
</main>
</body>
</html>

<?php

namespace Database\Seeders;

use App\Models\ConferenceSession;
use App\Models\Room;
use App\Models\SessionNote;
use App\Models\Speaker;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DemoScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $hosts = [
            'serena.conti@example.test' => User::query()->updateOrCreate(
                ['email' => 'serena.conti@example.test'],
                ['name' => 'Serena Conti', 'is_admin' => false, 'enabled' => true],
            ),
            'matteo.riva@example.test' => User::query()->updateOrCreate(
                ['email' => 'matteo.riva@example.test'],
                ['name' => 'Matteo Riva', 'is_admin' => false, 'enabled' => true],
            ),
        ];

        $rooms = [
            'grand-hall' => Room::query()->updateOrCreate(
                ['sessionize_id' => 'demo-room-grand-hall'],
                ['name' => 'Grand Hall'],
            ),
            'studio-uno' => Room::query()->updateOrCreate(
                ['sessionize_id' => 'demo-room-studio-uno'],
                ['name' => 'Studio Uno'],
            ),
        ];

        $speakers = [];

        foreach ([
            ['id' => 'demo-speaker-giulia-ferri', 'name' => 'Giulia Ferri', 'tagline' => 'CTO e facilitatrice di team prodotto', 'bio' => 'Giulia guida team che trasformano sistemi complessi in prodotti leggibili e durevoli.', 'photo' => 'https://i.pravatar.cc/300?img=12'],
            ['id' => 'demo-speaker-luca-serra', 'name' => 'Luca Serra', 'tagline' => 'Principal engineer', 'bio' => 'Luca lavora su piattaforme PHP ad alta affidabilità e su pratiche di revisione del codice.', 'photo' => 'https://i.pravatar.cc/300?img=13'],
            ['id' => 'demo-speaker-amina-khan', 'name' => 'Amina Khan', 'tagline' => 'Staff product designer', 'bio' => 'Amina progetta strumenti operativi accessibili per squadre distribuite.', 'photo' => 'https://i.pravatar.cc/300?img=47'],
            ['id' => 'demo-speaker-davide-gori', 'name' => 'Davide Gori', 'tagline' => 'Platform architect', 'bio' => 'Davide aiuta organizzazioni in crescita a rendere osservabili i loro sistemi.', 'photo' => 'https://i.pravatar.cc/300?img=14'],
            ['id' => 'demo-speaker-marta-bianchi', 'name' => 'Marta Bianchi', 'tagline' => 'Engineering manager', 'bio' => 'Marta costruisce rituali di team che migliorano qualità e autonomia.', 'photo' => 'https://i.pravatar.cc/300?img=45'],
            ['id' => 'demo-speaker-riccardo-villa', 'name' => 'Riccardo Villa', 'tagline' => 'Security engineer', 'bio' => 'Riccardo rende la sicurezza una pratica concreta nella vita quotidiana dei team.', 'photo' => 'https://i.pravatar.cc/300?img=15'],
            ['id' => 'demo-speaker-elena-mori', 'name' => 'Elena Mori', 'tagline' => 'Developer advocate', 'bio' => 'Elena racconta sistemi complessi con esempi concreti e linguaggio diretto.', 'photo' => 'https://i.pravatar.cc/300?img=32'],
            ['id' => 'demo-speaker-paolo-russo', 'name' => 'Paolo Russo', 'tagline' => 'Data engineer', 'bio' => 'Paolo progetta flussi dati che restano comprensibili anche sotto pressione.', 'photo' => 'https://i.pravatar.cc/300?img=16'],
        ] as $speaker) {
            $speakers[$speaker['id']] = Speaker::query()->updateOrCreate(
                ['sessionize_id' => $speaker['id']],
                [
                    'name' => $speaker['name'],
                    'tagline' => $speaker['tagline'],
                    'bio' => $speaker['bio'],
                    'photo_url' => $speaker['photo'],
                    'links' => [['title' => 'Profilo relatore', 'url' => 'https://example.test/speakers/'.$speaker['id']]],
                ],
            );
        }

        foreach ($this->sessions() as $sessionData) {
            $conferenceSession = ConferenceSession::query()->updateOrCreate(
                ['sessionize_id' => $sessionData['id']],
                [
                    'room_id' => $rooms[$sessionData['room']]->id,
                    'title' => $sessionData['title'],
                    'description' => $sessionData['description'],
                    'starts_at' => $this->utc($sessionData['starts_at']),
                    'ends_at' => $this->utc($sessionData['ends_at']),
                    'status' => 'Accepted',
                    'is_confirmed' => true,
                    'is_service_session' => $sessionData['service'],
                    'is_plenum_session' => $sessionData['plenum'],
                    'categories' => $sessionData['categories'],
                    'mc_description' => $sessionData['mc_description'],
                    'mc_script' => $sessionData['mc_script'],
                ],
            );

            $conferenceSession->speakers()->sync(
                collect($sessionData['speakers'])
                    ->mapWithKeys(fn (string $speakerId, int $sortOrder): array => [
                        $speakers[$speakerId]->id => ['sort_order' => $sortOrder],
                    ])
                    ->all(),
            );
            $conferenceSession->mcs()->sync(
                collect($sessionData['hosts'])
                    ->map(fn (string $email): int => $hosts[$email]->id)
                    ->all(),
            );

            if ($sessionData['note'] !== null) {
                SessionNote::query()->firstOrCreate([
                    'conference_session_id' => $conferenceSession->id,
                    'user_id' => $hosts[$sessionData['hosts'][0]]->id,
                    'body' => $sessionData['note'],
                ]);
            }
        }
    }

    /**
     * @return list<array{id: string, room: string, title: string, description: string, starts_at: string, ends_at: string, service: bool, plenum: bool, categories: list<string>, mc_description: string, mc_script: string, speakers: list<string>, hosts: list<string>, note: ?string}>
     */
    private function sessions(): array
    {
        return [
            ['id' => 'demo-session-opening-keynote', 'room' => 'grand-hall', 'title' => 'Keynote di apertura: costruire fiducia nei sistemi complessi', 'description' => 'Un’apertura dedicata a come le persone, i processi e il software possono rendere affidabile un prodotto nel tempo.', 'starts_at' => '2027-10-14 09:00', 'ends_at' => '2027-10-14 09:40', 'service' => false, 'plenum' => true, 'categories' => ['Keynote', 'Leadership'], 'mc_description' => 'Accogliere il pubblico, presentare la scaletta delle due giornate e introdurre Giulia.', 'mc_script' => 'Buongiorno e benvenuti. Iniziamo da una domanda semplice: cosa rende affidabile un sistema quando cambia tutto intorno?', 'speakers' => ['demo-speaker-giulia-ferri'], 'hosts' => ['serena.conti@example.test'], 'note' => 'Verificare che il timer di palco sia visibile prima dell’ingresso di Giulia.'],
            ['id' => 'demo-session-php-under-pressure', 'room' => 'grand-hall', 'title' => 'PHP sotto pressione: progettare confini che resistono', 'description' => 'Pattern pratici per mantenere leggibili applicazioni PHP che crescono insieme al team.', 'starts_at' => '2027-10-14 10:00', 'ends_at' => '2027-10-14 10:45', 'service' => false, 'plenum' => false, 'categories' => ['Backend', 'PHP'], 'mc_description' => 'Ricordare le domande via QR code e annunciare la pausa successiva.', 'mc_script' => 'Luca ci porta dentro i confini che permettono al codice di restare semplice anche nei momenti più intensi.', 'speakers' => ['demo-speaker-luca-serra'], 'hosts' => ['serena.conti@example.test'], 'note' => null],
            ['id' => 'demo-session-accessible-operations', 'room' => 'studio-uno', 'title' => 'Strumenti operativi accessibili per team distribuiti', 'description' => 'Come progettare interfacce e rituali che funzionano per tutte le persone del team.', 'starts_at' => '2027-10-14 10:00', 'ends_at' => '2027-10-14 10:45', 'service' => false, 'plenum' => false, 'categories' => ['Design', 'Accessibilità'], 'mc_description' => 'Controllare che la sala abbia i posti riservati liberi.', 'mc_script' => 'Amina ci mostra come piccoli dettagli di progetto cambiano il lavoro quotidiano di un intero team.', 'speakers' => ['demo-speaker-amina-khan'], 'hosts' => ['matteo.riva@example.test'], 'note' => null],
            ['id' => 'demo-session-lunch-handover', 'room' => 'grand-hall', 'title' => 'Pranzo e passaggio di microfono', 'description' => 'Pausa pranzo con indicazioni logistiche e passaggio della conduzione del pomeriggio.', 'starts_at' => '2027-10-14 12:30', 'ends_at' => '2027-10-14 14:00', 'service' => true, 'plenum' => true, 'categories' => ['Servizio'], 'mc_description' => 'Serena chiude la mattina; Matteo riprende la conduzione alle 13:55.', 'mc_script' => 'Ci fermiamo per pranzo. Serena vi saluta qui; al rientro Matteo aprirà il pomeriggio in Grand Hall.', 'speakers' => [], 'hosts' => ['serena.conti@example.test', 'matteo.riva@example.test'], 'note' => 'Matteo deve essere al palco cinque minuti prima della ripresa.'],
            ['id' => 'demo-session-observable-platforms', 'room' => 'grand-hall', 'title' => 'Osservabilità che aiuta davvero a decidere', 'description' => 'Un percorso concreto per trasformare metriche e log in conversazioni utili al prodotto.', 'starts_at' => '2027-10-14 14:00', 'ends_at' => '2027-10-14 14:45', 'service' => false, 'plenum' => false, 'categories' => ['Platform', 'Observability'], 'mc_description' => 'Aprire con un breve richiamo al rientro dal pranzo.', 'mc_script' => 'Bentornati. Davide ci aiuta a distinguere i segnali utili dal rumore operativo.', 'speakers' => ['demo-speaker-davide-gori'], 'hosts' => ['matteo.riva@example.test'], 'note' => null],
            ['id' => 'demo-session-team-rituals', 'room' => 'studio-uno', 'title' => 'Rituali di team che fanno spazio al lavoro profondo', 'description' => 'Una sessione su feedback, decisioni e responsabilità condivisa.', 'starts_at' => '2027-10-14 14:00', 'ends_at' => '2027-10-14 14:45', 'service' => false, 'plenum' => false, 'categories' => ['Leadership', 'Team'], 'mc_description' => 'Invitare il pubblico a proseguire il confronto nel foyer.', 'mc_script' => 'Marta porta esempi di rituali piccoli, ripetibili e utili quando il ritmo aumenta.', 'speakers' => ['demo-speaker-marta-bianchi'], 'hosts' => ['serena.conti@example.test'], 'note' => null],
            ['id' => 'demo-session-security-habits', 'room' => 'grand-hall', 'title' => 'Sicurezza come abitudine, non come blocco', 'description' => 'Pratiche quotidiane per rendere le decisioni sicure senza rallentare le consegne.', 'starts_at' => '2027-10-15 09:30', 'ends_at' => '2027-10-15 10:15', 'service' => false, 'plenum' => false, 'categories' => ['Security', 'Engineering'], 'mc_description' => 'Ricordare che la domanda finale verrà raccolta alla fine della sessione.', 'mc_script' => 'Riccardo ci accompagna in una sicurezza concreta: poche pratiche, ripetute bene.', 'speakers' => ['demo-speaker-riccardo-villa'], 'hosts' => ['matteo.riva@example.test'], 'note' => null],
            ['id' => 'demo-session-data-stories', 'room' => 'studio-uno', 'title' => 'Raccontare i dati senza perdere le persone', 'description' => 'Tecniche per trasformare una pipeline dati in una storia che il team può usare.', 'starts_at' => '2027-10-15 09:30', 'ends_at' => '2027-10-15 10:15', 'service' => false, 'plenum' => false, 'categories' => ['Data', 'Communication'], 'mc_description' => 'Controllare che il relatore abbia il clicker.', 'mc_script' => 'Paolo unisce dati e linguaggio: vediamo come rendere una pipeline una conversazione.', 'speakers' => ['demo-speaker-paolo-russo'], 'hosts' => ['serena.conti@example.test'], 'note' => null],
            ['id' => 'demo-session-closing-keynote', 'room' => 'grand-hall', 'title' => 'Keynote finale: scegliere cosa rendere semplice domani', 'description' => 'Una chiusura per trasformare gli spunti emersi in esperimenti concreti da portare al lavoro.', 'starts_at' => '2027-10-15 16:00', 'ends_at' => '2027-10-15 16:45', 'service' => false, 'plenum' => true, 'categories' => ['Keynote', 'Community'], 'mc_description' => 'Ringraziare il pubblico e introdurre Elena come voce conclusiva.', 'mc_script' => 'Per chiudere questi due giorni, Elena ci invita a scegliere una sola cosa da rendere più semplice già domani.', 'speakers' => ['demo-speaker-elena-mori'], 'hosts' => ['matteo.riva@example.test'], 'note' => 'Lasciare spazio per applausi e foto finale prima dei saluti.'],
        ];
    }

    private function utc(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $dateTime, 'Europe/Rome')->utc();
    }
}

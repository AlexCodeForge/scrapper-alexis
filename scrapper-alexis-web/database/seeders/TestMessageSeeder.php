<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Message;
use App\Models\Profile;
use Illuminate\Support\Str;

class TestMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create a test profile
        $profile = Profile::firstOrCreate(
            ['url' => 'https://facebook.com/testprofile'],
            [
                'username' => 'Test Profile',
                'credentials_reference' => null,
                'is_active' => true,
            ]
        );

        $messages = [
            'Ya quiero que sea fin de semana para poder descansar',
            'El café de la mañana es lo mejor del día',
            'No puedo creer que ya sea viernes',
            'Me encanta cuando llueve por las noches',
            'Los tacos al pastor son los mejores',
            'Hoy es un día perfecto para quedarse en casa',
            'La pizza con piña es deliciosa no me importa que digan',
            'Extraño las vacaciones ya quiero que sea verano',
            'Me gusta mucho escuchar música mientras trabajo',
            'El chocolate oscuro es mejor que el chocolate con leche',
            'Los gatos son mejores mascotas que los perros',
            'Me encanta ver películas de terror en la noche',
            'La comida mexicana es la mejor del mundo',
            'Prefiero el invierno que el verano cualquier día',
            'Los videojuegos son una forma de arte moderna',
            'Me gusta más el té que el café',
            'Las series de Netflix son mejores que las películas',
            'Me encanta cocinar los fines de semana',
            'Prefiero leer libros físicos que digitales',
            'La música en vivo es una experiencia única',
            'Me gusta más la playa que la montaña',
            'Los tacos de carnitas son los mejores de todos',
            'Prefiero el día que la noche para salir',
            'Me encanta el olor de la lluvia fresca',
            'Los videojuegos retro son los mejores',
            'Prefiero viajar en avión que en carro',
            'La comida casera siempre es mejor que la de restaurante',
            'Me gusta más el verano que el invierno',
            'Los perros son mejores compañeros que los gatos',
            'Prefiero el cine que ver películas en casa',
            'Me encanta el sabor del café sin azúcar',
            'La música electrónica es genial para concentrarse',
            'Prefiero las ciudades grandes a los pueblos pequeños',
            'Me gusta más correr que ir al gimnasio',
            'Los tacos de pescado son subestimados',
            'Prefiero el silencio a la música de fondo',
            'Me encanta el aroma del pan recién horneado',
            'Las comedias románticas son mis películas favoritas',
            'Prefiero escribir a mano que en computadora',
            'Me gusta más el campo que la ciudad',
            'Los videojuegos multijugador son más divertidos',
            'Prefiero madrugar que desvelarme trabajando',
            'Me encanta el sabor de la comida picante',
            'La fotografía análoga tiene más alma',
            'Prefiero caminar que usar transporte público',
            'Me gusta más la primavera que el otoño',
            'Los tacos dorados son una delicia mexicana',
            'Prefiero las películas clásicas a las modernas',
            'Me encanta el sonido de las olas del mar',
            'La música clásica ayuda a estudiar mejor',
        ];

        // Create 50 test messages with images
        for ($i = 0; $i < 50; $i++) {
            $messageText = $messages[$i % count($messages)];
            $timestamp = now()->subHours(rand(1, 48));

            // Random image from placeholder service
            $imageWidth = rand(400, 800);
            $imageHeight = rand(300, 600);
            $imagePath = "test_image_{$i}_{$imageWidth}x{$imageHeight}.png";

            // Random approval status: 40% pending, 30% approved, 30% rejected
            $rand = rand(1, 100);
            if ($rand <= 40) {
                // Pending
                $approvedForPosting = false;
                $approvedAt = null;
            } elseif ($rand <= 70) {
                // Approved
                $approvedForPosting = true;
                $approvedAt = now()->subHours(rand(1, 24));
            } else {
                // Rejected
                $approvedForPosting = false;
                $approvedAt = now()->subHours(rand(1, 24));
            }

            Message::create([
                'profile_id' => $profile->id,
                'message_text' => $messageText,
                'message_hash' => md5($messageText . $timestamp),
                'scraped_at' => $timestamp,
                'posted_to_twitter' => false,
                'posted_at' => null,
                'post_url' => null,
                'avatar_url' => "https://i.pravatar.cc/150?img={$i}",
                'image_generated' => true,
                'image_path' => $imagePath,
                'downloaded' => false,
                'downloaded_at' => null,
                'approved_for_posting' => $approvedForPosting,
                'approved_at' => $approvedAt,
                'posted_to_page' => false,
                'posted_to_page_at' => null,
            ]);
        }

        $this->command->info('✅ Created 50 test messages with images');
        $this->command->info('   - ~20 pending approval');
        $this->command->info('   - ~15 approved');
        $this->command->info('   - ~15 rejected');
    }
}


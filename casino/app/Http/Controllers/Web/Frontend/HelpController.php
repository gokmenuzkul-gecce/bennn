<?php

namespace VanguardLTE\Http\Controllers\Web\Frontend;

use VanguardLTE\Http\Controllers\Controller;

class HelpController extends Controller
{
    private const LANGUAGES = [
        'en' => 'EN', 'fr' => 'FR', 'es' => 'ES', 'ru' => 'RU', 'tr' => 'TR', 'ar' => 'AR', 'he' => 'HE',
    ];

    public function index(?string $locale = 'en')
    {
        $locale = array_key_exists($locale, self::LANGUAGES) ? $locale : 'en';
        $content = $this->content()[$locale];

        return view('frontend.Minimal.help.index', [
            'locale' => $locale,
            'languages' => self::LANGUAGES,
            'content' => $content,
            'rtl' => in_array($locale, ['ar', 'he'], true),
        ]);
    }

    private function content(): array
    {
        return [
            'en' => [
                'title' => 'Player Help',
                'intro' => 'A quick guide to the lobby. Some features depend on the operator configuration, so you may not see every area listed here.',
                'sections' => [
                    ['icon' => 'casino', 'title' => 'Casino Slots', 'body' => 'Browse the slot library, choose a title, and press Play. Each game shows its own controls and rules; use your social-coin balance for play.'],
                    ['icon' => 'rocket_launch', 'title' => 'CEDAR Games', 'body' => 'Open the original arcade games such as Crash, Plinko, Mines, Dice, and more. Read the on-screen rules before each round and choose your stake when the game is available.'],
                    ['icon' => 'park', 'title' => 'CEDAR Slots', 'body' => 'Explore the CEDAR slot collection. Select a game from the catalog, set the available bet options, and use its in-game controls to play.'],
                    ['icon' => 'sports_soccer', 'title' => 'Battle Odds', 'body' => 'If the sportsbook is enabled, choose a fixture and add one or more outcomes to your bet slip. Review the stake, displayed odds, and potential return before placing a social wager.'],
                    ['icon' => 'auto_awesome', 'title' => 'Jackpot Zone', 'body' => 'If jackpots are enabled, select numbers for an upcoming draw and confirm your entry. Results are tied to the shown draw round; check the page after settlement for outcomes.'],
                    ['icon' => 'query_stats', 'title' => 'Future Vote', 'body' => 'If prediction markets are enabled, review the question and choose the outcome you support. The page shows the available price and potential result before you confirm.'],
                    ['icon' => 'candlestick_chart', 'title' => 'Crypto Trading', 'body' => 'If Crypto Trading is enabled, choose an asset, direction, stake, leverage, and round interval. This is a social-coin simulation: your position settles from the recorded round price, not a real trade.'],
                    ['icon' => 'trending_up', 'title' => 'Stock Trading', 'body' => 'If Stock Trading is enabled, choose a listed stock and a direction for the available round. Review the stake and settlement time; results use the recorded price snapshots for that round.'],
                    ['icon' => 'groups', 'title' => 'Affiliates', 'body' => 'Share your referral link to invite new players when the affiliate program is active. Your dashboard explains eligible referrals, levels, and any rewards you have earned.'],
                    ['icon' => 'workspace_premium', 'title' => 'VIP Club', 'body' => 'If VIP is enabled, visit this area to view your level, progress, and available benefits. Rewards and eligibility are shown in your account.'],
                ],
            ],
            'fr' => [
                'title' => 'Aide aux joueurs',
                'intro' => 'Guide rapide du lobby. Certaines fonctions dépendent de la configuration de l’opérateur et peuvent ne pas être visibles.',
                'sections' => [
                    ['icon'=>'casino','title'=>'Machines à sous','body'=>'Parcourez la bibliothèque, choisissez un jeu puis appuyez sur Jouer. Chaque jeu affiche ses propres commandes et règles; jouez avec votre solde de pièces sociales.'],
                    ['icon'=>'rocket_launch','title'=>'Jeux CEDAR','body'=>'Ouvrez les jeux arcade originaux, comme Crash, Plinko, Mines et Dice. Lisez les règles à l’écran avant chaque manche et choisissez votre mise si le jeu est disponible.'],
                    ['icon'=>'park','title'=>'Machines CEDAR','body'=>'Explorez la collection de machines CEDAR. Sélectionnez un jeu, réglez les mises disponibles et utilisez les commandes du jeu.'],
                    ['icon'=>'sports_soccer','title'=>'Battle Odds','body'=>'Si le sportsbook est activé, choisissez un match et ajoutez un ou plusieurs résultats au coupon. Vérifiez la mise, les cotes affichées et le retour potentiel avant de confirmer.'],
                    ['icon'=>'auto_awesome','title'=>'Zone Jackpot','body'=>'Si les jackpots sont activés, choisissez des numéros pour un prochain tirage et confirmez votre participation. Consultez la page après le règlement du tirage.'],
                    ['icon'=>'query_stats','title'=>'Future Vote','body'=>'Si les marchés prédictifs sont activés, lisez la question et choisissez le résultat que vous soutenez. Le prix disponible et le résultat potentiel sont affichés avant confirmation.'],
                    ['icon'=>'candlestick_chart','title'=>'Trading crypto','body'=>'Si cette fonction est activée, choisissez un actif, une direction, une mise, un levier et un intervalle. C’est une simulation en pièces sociales, pas une transaction réelle.'],
                    ['icon'=>'trending_up','title'=>'Trading actions','body'=>'Si cette fonction est activée, choisissez une action et une direction pour la manche disponible. Les résultats utilisent les relevés de prix enregistrés pour cette manche.'],
                    ['icon'=>'groups','title'=>'Affiliés','body'=>'Partagez votre lien de parrainage pour inviter des joueurs si le programme est actif. Le tableau de bord indique les parrainages, niveaux et récompenses éligibles.'],
                    ['icon'=>'workspace_premium','title'=>'Club VIP','body'=>'Si le VIP est activé, consultez votre niveau, votre progression et vos avantages disponibles. Les récompenses et conditions figurent dans votre compte.'],
                ],
            ],
            'es' => [
                'title' => 'Ayuda para jugadores',
                'intro' => 'Guía rápida del lobby. Algunas funciones dependen de la configuración del operador y quizá no aparezcan.',
                'sections' => [
                    ['icon'=>'casino','title'=>'Slots de casino','body'=>'Explora la biblioteca, elige un juego y pulsa Jugar. Cada juego muestra sus propios controles y reglas; utiliza tu saldo de monedas sociales.'],
                    ['icon'=>'rocket_launch','title'=>'Juegos CEDAR','body'=>'Abre los juegos arcade originales, como Crash, Plinko, Mines y Dice. Lee las reglas en pantalla antes de cada ronda y elige tu apuesta si el juego está disponible.'],
                    ['icon'=>'park','title'=>'Slots CEDAR','body'=>'Explora la colección de slots CEDAR. Elige un juego, configura las apuestas disponibles y usa sus controles para jugar.'],
                    ['icon'=>'sports_soccer','title'=>'Battle Odds','body'=>'Si el sportsbook está activo, elige un partido y añade uno o varios resultados al cupón. Revisa la apuesta, las cuotas y el retorno potencial antes de confirmar.'],
                    ['icon'=>'auto_awesome','title'=>'Zona Jackpot','body'=>'Si los jackpots están activos, elige números para un próximo sorteo y confirma tu entrada. Consulta la página después de la liquidación para ver los resultados.'],
                    ['icon'=>'query_stats','title'=>'Future Vote','body'=>'Si los mercados de predicción están activos, revisa la pregunta y elige el resultado que apoyas. La página muestra el precio y el resultado potencial antes de confirmar.'],
                    ['icon'=>'candlestick_chart','title'=>'Trading cripto','body'=>'Si está activo, elige un activo, dirección, apuesta, apalancamiento e intervalo. Es una simulación con monedas sociales, no una operación real.'],
                    ['icon'=>'trending_up','title'=>'Trading de acciones','body'=>'Si está activo, elige una acción y una dirección para la ronda disponible. Los resultados usan las capturas de precio registradas para esa ronda.'],
                    ['icon'=>'groups','title'=>'Afiliados','body'=>'Comparte tu enlace de referido para invitar jugadores si el programa está activo. El panel muestra referidos, niveles y recompensas que hayas obtenido.'],
                    ['icon'=>'workspace_premium','title'=>'Club VIP','body'=>'Si VIP está activo, visita esta área para ver tu nivel, progreso y beneficios disponibles. Las recompensas y requisitos aparecen en tu cuenta.'],
                ],
            ],
            'ru' => [
                'title' => 'Помощь игроку',
                'intro' => 'Краткое руководство по лобби. Некоторые функции зависят от настроек оператора, поэтому могут быть недоступны.',
                'sections' => [
                    ['icon'=>'casino','title'=>'Казино-слоты','body'=>'Откройте библиотеку, выберите игру и нажмите «Играть». У каждой игры свои правила и элементы управления; используется баланс социальных монет.'],
                    ['icon'=>'rocket_launch','title'=>'Игры CEDAR','body'=>'Откройте оригинальные аркадные игры: Crash, Plinko, Mines, Dice и другие. Перед раундом прочитайте правила на экране и выберите ставку, если игра доступна.'],
                    ['icon'=>'park','title'=>'Слоты CEDAR','body'=>'Изучите коллекцию слотов CEDAR. Выберите игру, настройте доступные ставки и используйте игровые элементы управления.'],
                    ['icon'=>'sports_soccer','title'=>'Battle Odds','body'=>'Если букмекерский раздел включён, выберите матч и добавьте один или несколько исходов в купон. Перед подтверждением проверьте ставку, коэффициенты и возможный результат.'],
                    ['icon'=>'auto_awesome','title'=>'Зона джекпота','body'=>'Если джекпоты включены, выберите числа для ближайшего тиража и подтвердите участие. После расчёта тиража проверьте страницу с результатами.'],
                    ['icon'=>'query_stats','title'=>'Future Vote','body'=>'Если рынки прогнозов включены, изучите вопрос и выберите поддерживаемый исход. До подтверждения отображаются цена и возможный результат.'],
                    ['icon'=>'candlestick_chart','title'=>'Криптотрейдинг','body'=>'Если функция включена, выберите актив, направление, ставку, плечо и интервал раунда. Это симуляция за социальные монеты, а не реальная сделка.'],
                    ['icon'=>'trending_up','title'=>'Торговля акциями','body'=>'Если функция включена, выберите акцию и направление для доступного раунда. Итоги используют зафиксированные ценовые снимки этого раунда.'],
                    ['icon'=>'groups','title'=>'Партнёры','body'=>'Поделитесь своей реферальной ссылкой, чтобы приглашать игроков, если программа активна. Панель показывает подходящих рефералов, уровни и награды.'],
                    ['icon'=>'workspace_premium','title'=>'VIP-клуб','body'=>'Если VIP включён, здесь можно посмотреть уровень, прогресс и доступные преимущества. Награды и условия указаны в аккаунте.'],
                ],
            ],
            'tr' => [
                'title' => 'Oyuncu Yardımı',
                'intro' => 'Lobi için hızlı rehber. Bazı özellikler işletmeci ayarlarına bağlıdır; bu nedenle listelenen her alanı göremeyebilirsiniz.',
                'sections' => [
                    ['icon'=>'casino','title'=>'Casino Slotları','body'=>'Oyun kitaplığını gezin, bir oyun seçin ve Oyna’ya basın. Her oyunun kendi kontrolleri ve kuralları vardır; sosyal jeton bakiyenizi kullanırsınız.'],
                    ['icon'=>'rocket_launch','title'=>'CEDAR Oyunları','body'=>'Crash, Plinko, Mines, Dice ve diğer özgün arcade oyunlarını açın. Her turdan önce ekrandaki kuralları okuyun ve oyun açıksa bahsinizi seçin.'],
                    ['icon'=>'park','title'=>'CEDAR Slotları','body'=>'CEDAR slot koleksiyonunu keşfedin. Katalogdan bir oyun seçin, kullanılabilir bahis seçeneklerini ayarlayın ve oyun içi kontrolleri kullanın.'],
                    ['icon'=>'sports_soccer','title'=>'Battle Odds','body'=>'Spor bahisleri etkinse bir karşılaşma seçin ve bir veya daha fazla sonucu kuponunuza ekleyin. Onaylamadan önce bahsi, oranları ve olası sonucu gözden geçirin.'],
                    ['icon'=>'auto_awesome','title'=>'Jackpot Bölgesi','body'=>'Jackpotlar etkinse yaklaşan çekiliş için sayıları seçin ve katılımınızı onaylayın. Sonuçlar için çekiliş tamamlandıktan sonra sayfayı kontrol edin.'],
                    ['icon'=>'query_stats','title'=>'Future Vote','body'=>'Tahmin piyasaları etkinse soruyu inceleyin ve desteklediğiniz sonucu seçin. Onaydan önce mevcut fiyat ve olası sonuç gösterilir.'],
                    ['icon'=>'candlestick_chart','title'=>'Kripto İşlemleri','body'=>'Etkinse varlık, yön, bahis, kaldıraç ve tur aralığını seçin. Bu, sosyal jetonlarla yapılan bir simülasyondur; gerçek işlem değildir.'],
                    ['icon'=>'trending_up','title'=>'Hisse İşlemleri','body'=>'Etkinse listelenen bir hisse ve kullanılabilir tur için yön seçin. Sonuçlar, o tur için kaydedilen fiyat anlık görüntülerini kullanır.'],
                    ['icon'=>'groups','title'=>'Ortaklar','body'=>'Program etkinse yeni oyuncular davet etmek için referans bağlantınızı paylaşın. Paneliniz uygun yönlendirmeleri, seviyeleri ve kazandığınız ödülleri açıklar.'],
                    ['icon'=>'workspace_premium','title'=>'VIP Kulübü','body'=>'VIP etkinse seviyenizi, ilerlemenizi ve mevcut avantajları görmek için bu alanı ziyaret edin. Ödüller ve uygunluk hesabınızda gösterilir.'],
                ],
            ],
            'ar' => [
                'title' => 'مساعدة اللاعب',
                'intro' => 'دليل سريع للردهة. تعتمد بعض الميزات على إعدادات المشغّل، لذلك قد لا تظهر كل المناطق المذكورة هنا.',
                'sections' => [
                    ['icon'=>'casino','title'=>'خانات الكازينو','body'=>'تصفّح مكتبة الألعاب واختر لعبة ثم اضغط لعب. لكل لعبة أدواتها وقواعدها الخاصة؛ استخدم رصيد العملات الاجتماعية للعب.'],
                    ['icon'=>'rocket_launch','title'=>'ألعاب CEDAR','body'=>'افتح ألعاب الأركيد الأصلية مثل Crash وPlinko وMines وDice وغيرها. اقرأ القواعد على الشاشة قبل كل جولة واختر رهانك إذا كانت اللعبة متاحة.'],
                    ['icon'=>'park','title'=>'خانات CEDAR','body'=>'استكشف مجموعة خانات CEDAR. اختر لعبة من الكتالوج، واضبط خيارات الرهان المتاحة، ثم استخدم أدوات التحكم داخل اللعبة.'],
                    ['icon'=>'sports_soccer','title'=>'Battle Odds','body'=>'إذا كانت المراهنات الرياضية مفعّلة، اختر مباراة وأضف نتيجة واحدة أو أكثر إلى قسيمة الرهان. راجع الرهان والاحتمالات والعائد المحتمل قبل التأكيد.'],
                    ['icon'=>'auto_awesome','title'=>'منطقة الجاكبوت','body'=>'إذا كانت الجوائز الكبرى مفعّلة، اختر أرقام السحب القادم وأكّد مشاركتك. تحقّق من الصفحة بعد تسوية السحب لمعرفة النتائج.'],
                    ['icon'=>'query_stats','title'=>'Future Vote','body'=>'إذا كانت أسواق التوقعات مفعّلة، راجع السؤال واختر النتيجة التي تدعمها. تظهر القيمة والنتيجة المحتملة قبل التأكيد.'],
                    ['icon'=>'candlestick_chart','title'=>'تداول العملات الرقمية','body'=>'إذا كانت الميزة مفعّلة، اختر الأصل والاتجاه والرهان والرافعة وفترة الجولة. هذه محاكاة بعملات اجتماعية وليست صفقة حقيقية.'],
                    ['icon'=>'trending_up','title'=>'تداول الأسهم','body'=>'إذا كانت الميزة مفعّلة، اختر سهماً واتجاهاً للجولة المتاحة. تستخدم النتائج لقطات الأسعار المسجلة لتلك الجولة.'],
                    ['icon'=>'groups','title'=>'الشركاء','body'=>'شارك رابط الإحالة لدعوة لاعبين جدد إذا كان البرنامج مفعّلاً. تشرح لوحة التحكم الإحالات المؤهلة والمستويات والمكافآت المكتسبة.'],
                    ['icon'=>'workspace_premium','title'=>'نادي VIP','body'=>'إذا كان VIP مفعّلاً، زر هذه المنطقة لعرض مستواك وتقدمك ومزاياك المتاحة. تظهر المكافآت والأهلية في حسابك.'],
                ],
            ],
            'he' => [
                'title' => 'עזרה לשחקנים',
                'intro' => 'מדריך מהיר ללובי. חלק מהתכונות תלויות בהגדרות המפעיל, ולכן ייתכן שלא כל האזורים המופיעים כאן יהיו זמינים.',
                'sections' => [
                    ['icon'=>'casino','title'=>'סלוטים בקזינו','body'=>'עיינו בספריית המשחקים, בחרו משחק ולחצו על שחק. לכל משחק כללים ופקדים משלו; המשחק נעשה באמצעות יתרת המטבעות החברתיים.'],
                    ['icon'=>'rocket_launch','title'=>'משחקי CEDAR','body'=>'פתחו את משחקי הארקייד המקוריים כגון Crash, Plinko, Mines ו-Dice. קראו את הכללים על המסך לפני כל סיבוב ובחרו הימור כאשר המשחק זמין.'],
                    ['icon'=>'park','title'=>'סלוטים של CEDAR','body'=>'גלו את אוסף הסלוטים של CEDAR. בחרו משחק מהקטלוג, הגדירו את אפשרויות ההימור הזמינות והשתמשו בפקדי המשחק.'],
                    ['icon'=>'sports_soccer','title'=>'Battle Odds','body'=>'אם אזור הספורט פעיל, בחרו משחק והוסיפו תוצאה אחת או יותר לטופס. בדקו את ההימור, היחסים והתוצאה האפשרית לפני האישור.'],
                    ['icon'=>'auto_awesome','title'=>'אזור ג׳קפוט','body'=>'אם הג׳קפוטים פעילים, בחרו מספרים להגרלה הקרובה ואשרו את השתתפותכם. בדקו את הדף לאחר סיום ההגרלה לתוצאות.'],
                    ['icon'=>'query_stats','title'=>'Future Vote','body'=>'אם שווקי התחזיות פעילים, קראו את השאלה ובחרו את התוצאה שבה אתם תומכים. המחיר והתוצאה האפשרית מוצגים לפני האישור.'],
                    ['icon'=>'candlestick_chart','title'=>'מסחר בקריפטו','body'=>'אם התכונה פעילה, בחרו נכס, כיוון, הימור, מינוף ומרווח סיבוב. זו סימולציה במטבעות חברתיים ולא מסחר אמיתי.'],
                    ['icon'=>'trending_up','title'=>'מסחר במניות','body'=>'אם התכונה פעילה, בחרו מניה וכיוון לסיבוב הזמין. התוצאות משתמשות בצילומי מחיר שנשמרו עבור אותו סיבוב.'],
                    ['icon'=>'groups','title'=>'שותפים','body'=>'שתפו את קישור ההפניה שלכם כדי להזמין שחקנים חדשים אם התוכנית פעילה. לוח הבקרה מסביר הפניות זכאיות, דרגות ותגמולים שנצברו.'],
                    ['icon'=>'workspace_premium','title'=>'מועדון VIP','body'=>'אם VIP פעיל, בקרו באזור זה כדי לראות את הדרגה, ההתקדמות וההטבות הזמינות. התגמולים ותנאי הזכאות מוצגים בחשבון שלכם.'],
                ],
            ],
        ];
    }
}

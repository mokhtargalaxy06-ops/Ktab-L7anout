# [Ktab-L7anout](https://github.com/mokhtargalaxy06-ops/Ktab-L7anout)

[العربية](#ما-هو-مشروع-ktab-l7anout) · [Français](#guide-en-français) · [English](#english-guide) · [لقطات الواجهة](#لقطات-الواجهة-العربية) · [Correctifs récents](#correctifs-récents) · [Contact](#contact) · [Installation](#local-setup)

## Correctifs récents

- **Interface adaptée aux petits écrans.** Le menu se replie sur mobile, les formulaires s’ajustent à la largeur disponible, les fenêtres de dialogue défilent verticalement et les tableaux administratifs défilent dans leur propre zone. Treize écrans ont été vérifiés dans Chrome en français et en arabe, à cinq largeurs de 320 à 1 440 px.
- **Chiffres d’impact.** L’accueil distingue les dons simulés reçus et affectés des paiements hors plateforme confirmés par les épiciers. Une famille est comptée dès qu’une partie de sa dette a effectivement été réglée et confirmée, et elle n’est comptée qu’une fois. Le compteur des dossiers entièrement réglés évolue lorsque la dette est intégralement payée. [Correctif des chiffres d’impact](https://github.com/mokhtargalaxy06-ops/Ktab-L7anout/commit/451901e02e33c87ebaee8a66d793b269c0c7f6d7)
- **Progression du dossier.** Une dette de 300 MAD dont l’épicier a confirmé avoir reçu 250 MAD hors plateforme affiche 250 MAD reçus, 50 MAD restants et 83 % de progression.
- **Suivi de la contribution.** La promesse WhatsApp laisse le solde inchangé. L’épicier confirme le montant reçu avec un justificatif, ce qui réduit le solde. Le donateur retrouve la confirmation et le montant dans le détail de sa contribution.
- **Approbation et affichage des montants.** Le formulaire de confirmation affiche les montants en dirhams, tandis que l’API conserve les centimes. Si une dette portant la même référence a déjà été approuvée ou publiée pour la même épicerie, l’interface explique le blocage en français et en arabe.

**English.** The homepage now separates simulated donations from shop-confirmed external payments. It counts each family once as soon as part of its debt has been paid and confirmed. Case pages show what the shop received and what remains. A WhatsApp pledge leaves the balance unchanged until the shop confirms receipt with evidence. Donors can track the confirmation in their contribution details. Debt confirmation displays amounts in dirhams, and duplicate approval errors identify the conflict.

**Responsive layout.** The mobile menu collapses, forms fit smaller screens, dialogs scroll vertically, and administrator tables scroll within their containers. Thirteen screens were checked in Chrome in French and Arabic at five widths from 320 to 1,440 px.

<div dir="rtl">

**الشاشات الصغيرة.** تُطوى قائمة التنقّل على الهاتف، وتتكيف النماذج مع عرض الشاشة، ويمكن تمرير النوافذ الحوارية عمودياً والجداول الإدارية أفقياً داخل مساحتها. جرى التحقق من 13 شاشة في Chrome بالعربية والفرنسية على خمسة مقاسات من 320 إلى 1440 بكسل.

**بالعربية.** تفصل الصفحة الرئيسية بين التبرعات المحاكية والمدفوعات خارج المنصة التي أكّد البقالون استلامها. تُحتسب كل أسرة مرة واحدة بمجرد تسديد جزء من دينها وتأكيده. يوضح الملف المبلغ المستلم والرصيد المتبقي. يظل الرصيد كما هو عند تسجيل وعد عبر واتساب، وينخفض عندما يؤكد البقال الاستلام ويرفق الإثبات. يظهر التأكيد والمبلغ في تفاصيل مساهمة المتبرع. ويعرض نموذج تأكيد الدين المبالغ بالدرهم، وتوضح رسالة الخطأ سبب تعذّر اعتماد ملف مكرر.

</div>

## Contact

Pour toute question sur Ktab-L7anout ou pour proposer une collaboration, écrivez à Mokhtar Soumair.

For questions about Ktab-L7anout or to discuss a collaboration, email Mokhtar Soumair.

للاستفسار عن المشروع أو اقتراح تعاون، راسل مختار سومير.

[mokhtargalaxy06@gmail.com](mailto:mokhtargalaxy06@gmail.com)

[LinkedIn — Mokhtar Soumair](https://www.linkedin.com/in/mokhtar-soumair-4b8855394/)

## Contribuer au projet

Consultez le [guide de contribution](CONTRIBUTING.md) pour proposer une modification ou ouvrir une pull request. Pour signaler un problème ou proposer une amélioration, choisissez un modèle dans les [issues GitHub](https://github.com/mokhtargalaxy06-ops/Ktab-L7anout/issues/new/choose). Les contributions sont les bienvenues en français, en arabe et en anglais.

<div dir="rtl">

## ما هو مشروع Ktab-L7anout؟

Ktab-L7anout منصة تضامن غذائي في المغرب تساعد الأسر التي تواجه صعوبات مالية على تسوية ديون مشترياتها الأساسية لدى بقالات الأحياء (مول الحانوت). تبدأ العملية بطلب مساعدة، ثم يؤكد البقال المعني وجود الدين ومبلغه، وتراجع الإدارة الملف قبل الموافقة على نشره. يستطيع المتبرع المساهمة في ملف منشور أو دعم صندوق الديون المستعجلة. تُسدَّد المبالغ للبقالة الموثّقة، وتستفيد الأسرة من تخفيض الدين أو تسويته.

تدعم المنصة العربية بتخطيط من اليمين إلى اليسار والفرنسية، وتنظّم الطلبات حسب الجهات المغربية والمدن والجماعات المسجّلة. تظهر المبالغ بالدرهم المغربي وتُحفظ في النظام بالسنتيمات الصحيحة حتى تبقى الحسابات دقيقة.

### من يستخدم المنصة؟

- **الأسرة المستفيدة** تختار المدينة والبقالة، وتدخل مبلغ الدين وتشرح وضعها المالي، وترفق إثباتاً بصيغة PDF أو صورة، وتوافق على شروط الخصوصية قبل إرسال الطلب. يمكنها متابعة حالته من حسابها.
- **البقال** يسجّل بقالته ويرفع الوثائق المطلوبة. بعد ورود طلب تحقق يخصّ بقالته، يمكنه تأكيد الدين ومبلغه، أو الاعتراض على المبلغ، أو رفض الطلب مع توضيح السبب. ويتابع التسوية ويؤكّد الاستلام أو يبلّغ عن اختلاف.
- **الإدارة** تتحقق من البقالات، وتراجع الطلبات والوثائق وتأكيدات البقالين، وتوافق على الملفات المؤهلة ثم تنشرها. ويمكنها طلب معلومات إضافية أو معالجة النزاعات، ومراجعة التبرعات والتخصيصات والتسويات، والرجوع إلى سجل التدقيق.
- **المتبرع** يتصفح الملفات المنشورة ويبحث عنها حسب الموقع والمبلغ والأولوية وحالة التمويل. يعرض الملف العام مرجعاً مجهول الهوية وموقعاً عاماً والدين المعتمد والتقدّم في تمويله. تظل وثائق الأسر الخاصة متاحة عبر صلاحيات محددة.

إذا كان الطلب قيد المراجعة أو معلّماً للمراجعة الإضافية، فلن يظهر في قائمة التبرّع. يظهر للمتبرعين بعد تأكيد الدين، وموافقة الإدارة، ونشر الملف. وعند البحث عن ملف منشور، تُدخل حدود المبلغ في صفحة الاستكشاف بالدرهم. إذا كانت قيمة الدين المعتمد 1000 سنتيم، فهي 10 دراهم، ولذلك يستبعدها مرشح يبدأ من 20 درهماً.

## كيف تتم عملية التبرّع؟

### المساهمة في ملف محدد عبر واتساب

1. سجّل الدخول بحساب متبرع وافتح ملفاً منشوراً متاحاً للتمويل. اختر مبلغاً مقترحاً أو أدخل مبلغاً خاصاً بالدرهم في حدود الرصيد المتبقي. يقبل النموذج الأرقام العربية مثل **١٢٫٥٠**.
2. اضغط على زر إرسال رسالة واتساب إلى البقالة. تفتح المحادثة على رقمها المهني مع رسالة جاهزة بلغة الواجهة تتضمن المبلغ ومرجع الملف. أرسل الرسالة من تطبيق واتساب.
3. ارجع إلى المنصة واضغط على زر تسجيل وعد المساهمة بعد الإرسال. يظهر المبلغ في سجل تبرعاتك بصفة «مساهمة موعودة». تعتمد المنصة على تصريحك بالإرسال، لأن رابط واتساب لا يرسل إليها تأكيداً بالتسليم.
4. افتح تفاصيل المساهمة في سجل التبرعات وشارك **مرجع المساهمة** مع البقال ليطابق الدفعة مع الوعد الصحيح. يظل الرصيد على حاله حتى يتأكد استلام الدفعة.
5. بعد استلام الدفعة خارج المنصة، يفتح البقال **مساحتي ← التسويات ← المدفوعات المستلمة خارج المنصة**، ويختار المساهمة، ويُدخل المبلغ المستلم ومرجع الدفع ويرفع إثباتاً بصيغة PDF أو صورة. عند الضغط على **«تأكيد استلام الدفع»** يُخصم المبلغ مباشرة دون انتظار موافقة إدارية. تُسجّل الدفعة في سجل مستقل عن الأموال التي تستلمها المنصة.
6. بعد تأكيد البقال، تعرض المنصة للمتبرع في **سجل التبرعات ← تفاصيل المساهمة** حالة **«تم تأكيد استلام الدفع»** والمبلغ الذي استلمه البقال فعلياً. إذا كانت الصفحة مفتوحة، حدّثها لعرض الحالة الجديدة. مثلاً، يبقى رصيد دين قدره **35 درهماً** كما هو عند تسجيل وعد بمبلغ **12,50 درهماً**. بعد تأكيد استلام **12,50 درهماً** يصبح الرصيد **22,50 درهماً**.

### صندوق الديون المستعجلة والدفع التجريبي

- يساهم المتبرع في صندوق مخصص للملفات المؤهلة، ويمكن توزيع المبلغ المؤكّد على ملف واحد أو عدة ملفات حسب الأولوية والرصيد المطلوب لكل ملف.
- تتيح المحاكاة في البيئات المهيأة لذلك اختبار نجاح الدفع أو تعليقه أو فشله أو إلغائه والاسترداد، دون بيانات بطاقات أو تحويل فعلي. يمنع النظام احتساب إشعار تأكيد مكرر.
- يُعاد الفائض في التبرّع المخصص لملف بعينه وفق السياسة المعروضة قبل الدفع. ويُدرج رصيد الصندوق غير المخصص للاسترداد بعد 30 يوماً من تأكيد الاستلام.
- يبيّن سجل التبرعات حالة الدفع والمبالغ المخصصة لكل ملف والاستردادات والتقدم في تسوية الدين لدى البقالة.

### كيف يحدّد الصندوق أولوية الملفات؟

يحسب النظام الأولوية من أربعة عوامل ذات أوزان قابلة للضبط. الأوزان المعتمدة مبدئياً هي هشاشة وضع الأسرة **35٪**، وعمر الدين **25٪**، وخطر فقدان الوصول إلى المواد الغذائية الأساسية **25٪**، والمبلغ المتبقي **15٪**. عند تعادل النقاط، يُقدّم الدين الأقدم، ثم الملف المنشور أولاً، ثم يُستخدم المعرّف الداخلي للملف لحسم التعادل. تحدّد الدرجة ترتيب تمويل الملفات المؤهلة، وتبقى قرارات القبول والرفض ضمن المراجعة البشرية.

### متى تُعدّ البقالة قد استلمت المبلغ؟

في **مسار الدفع خارج المنصة**، يؤكد البقال الاستلام بعد إضافة الإثبات، ويصبح الملف مسوّى إذا غطّت الدفعات كامل الدين. في **مسار التسوية عبر المنصة**، تبدأ التسوية بعد اكتمال تخصيص الأموال. تسجّل الإدارة مرجع التحويل وإثباته، ويؤكد البقال الاستلام أو يعترض، ثم تُطابق الإدارة السجلات قبل اعتبار الدين مسوّى.

| الحالة | ما تعنيه |
| --- | --- |
| مساهمة موعودة | سُجّل المبلغ الذي ينوي المتبرع دفعه. لم يُخصم من رصيد الدين بعد. |
| تم تأكيد الاستلام | أكد البقال دفعة خارج المنصة وأرفق إثباتاً. خُصم المبلغ من الرصيد، وقد يبقى جزء من الدين. |
| ممول بالكامل | المبالغ المخصصة تغطي الرصيد المطلوب، وقد تكون تسوية الدفعة عبر المنصة قيد التنفيذ. |
| مسوّى | سُدّد كامل الدين للبقالة وفق مسار الدفع المستخدم. |

يمكن العثور على الملفات الممولة أو المسوّاة من **الملفات المنشورة ← حالة التمويل**. وتعرض لوحة الشفافية مبالغ المنصة المستلمة والمخصصة والتحويلات المسجلة والمبالغ التي أكّدت البقالات استلامها، كلّاً على حدة.

### كيف بُني المشروع؟

- **الواجهة** مبنية باستخدام React وTypeScript وVite وTailwind CSS، وتضم صفحات عامة ومساحات مخصصة لكل دور.
- **الخادم** يوفّر واجهة REST باستخدام Laravel وSanctum، ويتحقق من المدخلات والصلاحيات على مستوى كل مورد.
- **قاعدة البيانات** تستخدم PostgreSQL. تُنفّذ العمليات المالية داخل معاملات تحمي الأرصدة من التخصيص المكرر أو تجاوز سقف الدين، وتُسجّل التعديلات المالية مع الإبقاء على تاريخ العمليات.

## خصائص المنصة

| المجال | الخصائص |
| --- | --- |
| الحسابات والصلاحيات | التسجيل حسب الدور، تسجيل الدخول والخروج، تعديل الملف الشخصي، رقم الهاتف، استعادة كلمة المرور، التحقق من البريد الإلكتروني وإدارة حالة الحساب. تُنشأ حسابات المديرين عبر إجراء إداري مخصّص. |
| الجهات والمدن | مرجع قابل للإدارة للجهات المغربية الاثنتي عشرة والمدن والجماعات المدعومة بالعربية والفرنسية. تُعرض المدن وفق الجهة المختارة، ويُربط كل متجر ودين بموقعه. |
| واجهة الزوّار | صفحة رئيسية تعرض أثر المنصة والملفات الحديثة والمستعجلة، وقائمة ملفات بصفحات متتالية مع البحث والتصفية حسب الجهة والمدينة والمبلغ والاستعجال وحالة التمويل. تعرض صفحة الملف المبلغ المعتمد والرصيد المتبقي والتقدّم والتحديثات المتاحة. |
| طلبات الأسر | إنشاء مسودة وتعديلها، تحديد البقالة والدين ووضع الأسرة، رفع PDF أو صورة، تسجيل الموافقة على الخصوصية وتتبع مراحل الطلب. يمكن إرسال الطلب بعد رفع المستند دون انتظار تحليله، إذا استوفى بقية الشروط. |
| مساحة البقال | تسجيل البقالة وإدارة بياناتها ووثائقها، استقبال الطلبات المرتبطة بها، تأكيد الدين أو رفضه أو الاعتراض على مبلغه، إرفاق إثبات، ومتابعة التسوية وتأكيد الاستلام أو الاعتراض عليها. |
| مراجعة الإدارة | التحقق من البقالات ورفضها أو تعليقها، مراجعة الإثباتات والتنبيه إلى الطلبات المحتمل تكرارها، طلب معلومات إضافية، اعتماد الملفات ونشرها أو إلغاء نشرها، ومتابعة النزاعات والحالات المعلّمة. |
| الخصوصية والوثائق | تخزين المرفقات بصورة خاصة والتنزيل وفق صلاحيات الدور والملف. يستطيع المتبرعون تنزيل النسخ المنزوعة البيانات التي تعتمد الإدارة مشاركتها من وثائق البقالة. |
| تمويل الديون | وعد مساهمة عبر واتساب لملف بعينه، وصندوق مستعجل في مسار الدفع التجريبي، وسجل للمدفوعات والتخصيصات والاستردادات. تُتابع كل مرحلة مالياً على حدة. |
| تسوية الديون | قائمة للتسويات والعمليات المتعثرة أو المتنازع عليها، ومراجعة إدارية وتسجيل مرجع التحويل والإثبات، ثم تأكيد البقال ومطابقة السجلات. |
| الشفافية والتقارير | مؤشرات عامة محسوبة من قاعدة البيانات للتمويل والتسويات والأسر المستفيدة والبقالات المشاركة والأثر حسب الجهة. تتيح الإدارة تصفية السجلات المالية وتصدير تقارير CSV ومراجعة سجل الإجراءات. |
| اللغة والأجهزة | واجهة بالعربية والفرنسية، واتجاه من اليمين إلى اليسار للغة العربية، وتنسيق مغربي للمبالغ والتواريخ، وصفحات ملائمة للهاتف والحاسوب. |
| التشغيل والحماية | فصل الواجهة عن الخادم، وتطبيق الصلاحيات والتحقق من المدخلات، وتقييد الطلبات الحساسة، وسجل مالي قابل للتدقيق. تشمل وثائق التشغيل إعدادات النشر والمهام المجدولة والمراقبة والنسخ الاحتياطي والاستعادة. |

للتشغيل على جهازك، اتبع [خطوات الإعداد المحلي](#local-setup). وللتفاصيل التقنية، راجع [بنية المشروع](docs/architecture.md) و[تصميم قاعدة البيانات](docs/database-design.md) و[قواعد التمويل والتسوية](docs/business-rules.md) و[إجراءات الحماية](docs/security.md).

</div>

## لقطات الواجهة العربية

يضمّ هذا المعرض 34 لقطة من صفحات التطبيق باللغة العربية على شاشة الحاسوب. يمكنك أيضاً تصفح [لقطات نسخة الهاتف](#لقطات-نسخة-الهاتف). الحسابات والطلبات والمبالغ الظاهرة بيانات تجريبية.

### الصفحات العامة

#### الصفحة الرئيسية

![الصفحة الرئيسية تعرض التبرعات المحاكية والمدفوعات المؤكدة خارج المنصة في عدّادين منفصلين](docs/screenshots/ar/accueil.jpg)

#### تصفح الملفات المنشورة

![تصفح الملفات المنشورة](docs/screenshots/ar/dossiers-publies.jpg)

#### تفاصيل ملف منشور

![تفاصيل ملف دين بقيمة 300 درهم، استلم البقال 250 درهماً وبقي 50 درهماً بنسبة تقدّم 83٪](docs/screenshots/ar/dossier-publie.jpg)

#### الملفات العاجلة

![الملفات العاجلة](docs/screenshots/ar/dossiers-urgents.jpg)

#### الشفافية المالية

![الشفافية المالية](docs/screenshots/ar/transparence.jpg)

### الحساب وتسجيل الدخول

#### تسجيل الدخول

![تسجيل الدخول](docs/screenshots/ar/connexion.jpg)

#### إنشاء حساب

![إنشاء حساب](docs/screenshots/ar/inscription.jpg)

#### طلب استعادة كلمة المرور

![طلب استعادة كلمة المرور](docs/screenshots/ar/mot-de-passe-oublie.jpg)

#### إعادة تعيين كلمة المرور

![إعادة تعيين كلمة المرور](docs/screenshots/ar/reinitialisation.jpg)

#### تأكيد البريد الإلكتروني

![تأكيد البريد الإلكتروني](docs/screenshots/ar/verification-email.jpg)

#### صفحة غير موجودة

![صفحة غير موجودة](docs/screenshots/ar/page-introuvable.jpg)

### مساحة المتبرع والمساهمات

#### مساحة المتبرع

![مساحة المتبرع](docs/screenshots/ar/espace-donateur.jpg)

#### المساهمة في ملف عبر واتساب

![المساهمة في ملف عبر واتساب](docs/screenshots/ar/contribution.jpg)

#### صندوق الديون المستعجلة

![صندوق الديون المستعجلة](docs/screenshots/ar/fonds-urgent.jpg)

#### سجل التبرعات

![سجل التبرعات](docs/screenshots/ar/historique-dons.jpg)

#### تفاصيل المساهمة وتأكيد البقال استلام الدفع

![تفاصيل المساهمة وتأكيد البقال استلام الدفع](docs/screenshots/ar/detail-contribution.jpg)

#### الدفع التجريبي

![الدفع التجريبي](docs/screenshots/ar/paiement-simule.jpg)

#### تأكيد الدفع التجريبي

![تأكيد الدفع التجريبي](docs/screenshots/ar/paiement-reussi.jpg)

#### فشل الدفع التجريبي

![فشل الدفع التجريبي](docs/screenshots/ar/paiement-echoue.jpg)

### مساحة المستفيد

#### مساحة المستفيد

![مساحة المستفيد](docs/screenshots/ar/espace-beneficiaire.jpg)

#### طلب مساعدة جديد

![طلب مساعدة جديد](docs/screenshots/ar/nouvelle-demande.jpg)

#### تتبع الطلب والوثائق

![تتبع الطلب والوثائق](docs/screenshots/ar/suivi-demande.jpg)

### مساحة البقال

#### مساحة البقال وتأكيد الدفعات المستلمة

![مساحة البقال وتأكيد الدفعات المستلمة](docs/screenshots/ar/espace-epicier.jpg)

#### تسجيل بقالة

![تسجيل بقالة](docs/screenshots/ar/inscription-epicerie.jpg)

#### التحقق من الدين

![التحقق من الدين](docs/screenshots/ar/verification-dette.jpg)

### مركز الإدارة

#### نظرة عامة

![نظرة عامة](docs/screenshots/ar/administration.jpg)

#### إدارة الملفات

![إدارة الملفات](docs/screenshots/ar/admin-dossiers.jpg)

#### مراجعة ملف

![مراجعة ملف](docs/screenshots/ar/admin-examen.jpg)

#### إدارة المستخدمين

![إدارة المستخدمين](docs/screenshots/ar/admin-utilisateurs.jpg)

#### إدارة البقالات

![إدارة البقالات](docs/screenshots/ar/admin-epiceries.jpg)

#### مراجعة ملف بقالة

![مراجعة ملف بقالة](docs/screenshots/ar/admin-fiche-epicerie.jpg)

#### الإدارة المالية

![الإدارة المالية](docs/screenshots/ar/admin-finances.jpg)

#### النشاط حسب الجهة

![النشاط حسب الجهة](docs/screenshots/ar/admin-territoires.jpg)

#### سجل التدقيق

![سجل التدقيق](docs/screenshots/ar/admin-audit.jpg)


## لقطات نسخة الهاتف

34 لقطة للواجهة العربية على شاشة بعرض 390 بكسل. تستخدم المساحات الخاصة حسابات وبيانات تجريبية.

### الصفحات العامة

| | |
| --- | --- |
| **الصفحة الرئيسية**<br><img src="docs/screenshots/ar/accueil-mobile.jpg" alt="الصفحة الرئيسية على الهاتف" width="260"> | **الملفات المنشورة**<br><img src="docs/screenshots/ar/dossiers-publies-mobile.jpg" alt="الملفات المنشورة على الهاتف" width="260"> |
| **تفاصيل ملف**<br><img src="docs/screenshots/ar/dossier-publie-mobile.jpg" alt="تفاصيل ملف على الهاتف" width="260"> | **الملفات العاجلة**<br><img src="docs/screenshots/ar/dossiers-urgents-mobile.jpg" alt="الملفات العاجلة على الهاتف" width="260"> |
| **الشفافية المالية**<br><img src="docs/screenshots/ar/transparence-mobile.jpg" alt="الشفافية المالية على الهاتف" width="260"> |  |

### الحساب

| | |
| --- | --- |
| **تسجيل الدخول**<br><img src="docs/screenshots/ar/connexion-mobile.jpg" alt="تسجيل الدخول على الهاتف" width="260"> | **إنشاء حساب**<br><img src="docs/screenshots/ar/inscription-mobile.jpg" alt="إنشاء حساب على الهاتف" width="260"> |
| **استعادة كلمة المرور**<br><img src="docs/screenshots/ar/mot-de-passe-oublie-mobile.jpg" alt="استعادة كلمة المرور على الهاتف" width="260"> | **إعادة تعيين كلمة المرور**<br><img src="docs/screenshots/ar/reinitialisation-mobile.jpg" alt="إعادة تعيين كلمة المرور على الهاتف" width="260"> |
| **تأكيد البريد الإلكتروني**<br><img src="docs/screenshots/ar/verification-email-mobile.jpg" alt="تأكيد البريد الإلكتروني على الهاتف" width="260"> | **صفحة غير موجودة**<br><img src="docs/screenshots/ar/page-introuvable-mobile.jpg" alt="صفحة غير موجودة على الهاتف" width="260"> |

### مساحة المتبرع والمساهمات

| | |
| --- | --- |
| **مساحة المتبرع**<br><img src="docs/screenshots/ar/espace-donateur-mobile.jpg" alt="مساحة المتبرع على الهاتف" width="260"> | **المساهمة عبر واتساب**<br><img src="docs/screenshots/ar/contribution-mobile.jpg" alt="المساهمة عبر واتساب على الهاتف" width="260"> |
| **صندوق الديون المستعجلة**<br><img src="docs/screenshots/ar/fonds-urgent-mobile.jpg" alt="صندوق الديون المستعجلة على الهاتف" width="260"> | **سجل التبرعات**<br><img src="docs/screenshots/ar/historique-dons-mobile.jpg" alt="سجل التبرعات على الهاتف" width="260"> |
| **تفاصيل المساهمة وتأكيد الاستلام**<br><img src="docs/screenshots/ar/detail-contribution-mobile.jpg" alt="تفاصيل المساهمة وتأكيد الاستلام على الهاتف" width="260"> | **الدفع التجريبي**<br><img src="docs/screenshots/ar/paiement-simule-mobile.jpg" alt="الدفع التجريبي على الهاتف" width="260"> |
| **نجاح الدفع التجريبي**<br><img src="docs/screenshots/ar/paiement-reussi-mobile.jpg" alt="نجاح الدفع التجريبي على الهاتف" width="260"> | **فشل الدفع التجريبي**<br><img src="docs/screenshots/ar/paiement-echoue-mobile.jpg" alt="فشل الدفع التجريبي على الهاتف" width="260"> |

### مساحة المستفيد

| | |
| --- | --- |
| **مساحة المستفيد**<br><img src="docs/screenshots/ar/espace-beneficiaire-mobile.jpg" alt="مساحة المستفيد على الهاتف" width="260"> | **طلب مساعدة جديد**<br><img src="docs/screenshots/ar/nouvelle-demande-mobile.jpg" alt="طلب مساعدة جديد على الهاتف" width="260"> |
| **تتبع طلب المساعدة**<br><img src="docs/screenshots/ar/suivi-demande-mobile.jpg" alt="تتبع طلب المساعدة على الهاتف" width="260"> |  |

### مساحة البقال

| | |
| --- | --- |
| **مساحة البقال وتأكيد المدفوعات**<br><img src="docs/screenshots/ar/espace-epicier-mobile.jpg" alt="مساحة البقال وتأكيد المدفوعات على الهاتف" width="260"> | **التحقق من الدين**<br><img src="docs/screenshots/ar/verification-dette-mobile.jpg" alt="التحقق من الدين على الهاتف" width="260"> |
| **تسجيل بقالة**<br><img src="docs/screenshots/ar/inscription-epicerie-mobile.jpg" alt="تسجيل بقالة على الهاتف" width="260"> |  |

### مركز الإدارة

| | |
| --- | --- |
| **نظرة عامة**<br><img src="docs/screenshots/ar/administration-mobile.jpg" alt="نظرة عامة على الهاتف" width="260"> | **إدارة الملفات**<br><img src="docs/screenshots/ar/admin-dossiers-mobile.jpg" alt="إدارة الملفات على الهاتف" width="260"> |
| **مراجعة ملف**<br><img src="docs/screenshots/ar/admin-examen-mobile.jpg" alt="مراجعة ملف على الهاتف" width="260"> | **إدارة المستخدمين**<br><img src="docs/screenshots/ar/admin-utilisateurs-mobile.jpg" alt="إدارة المستخدمين على الهاتف" width="260"> |
| **إدارة البقالات**<br><img src="docs/screenshots/ar/admin-epiceries-mobile.jpg" alt="إدارة البقالات على الهاتف" width="260"> | **مراجعة ملف بقالة**<br><img src="docs/screenshots/ar/admin-fiche-epicerie-mobile.jpg" alt="مراجعة ملف بقالة على الهاتف" width="260"> |
| **الإدارة المالية**<br><img src="docs/screenshots/ar/admin-finances-mobile.jpg" alt="الإدارة المالية على الهاتف" width="260"> | **الجهات**<br><img src="docs/screenshots/ar/admin-territoires-mobile.jpg" alt="الجهات على الهاتف" width="260"> |
| **سجل التدقيق**<br><img src="docs/screenshots/ar/admin-audit-mobile.jpg" alt="سجل التدقيق على الهاتف" width="260"> |  |

## Guide en français

**Ktab-L7anout** aide les familles au Maroc à régler leurs dettes alimentaires auprès des épiceries de quartier. La plateforme propose une interface en français et en arabe. Les visiteurs peuvent consulter les dossiers publiés, rechercher par ville ou région, filtrer par montant, urgence ou état du financement et consulter les indicateurs publics. Les fiches publiques sont anonymisées. Les justificatifs restent privés, avec des copies anonymisées téléchargeables lorsqu’elles sont partagées par l’administration.

### De la demande au dossier publié

1. **Épicier.** Inscrivez votre épicerie, renseignez ses coordonnées et joignez un justificatif. L’administration vérifie l’épicerie avant qu’elle puisse traiter une demande de dette.
2. **Bénéficiaire.** Dans **Mon espace**, créez une demande, choisissez la ville et l’épicerie, indiquez la référence et le montant de la dette en dirhams, puis enregistrez le brouillon. Téléversez un PDF ou une image, acceptez les conditions de confidentialité et cliquez sur **Soumettre**. L’envoi peut se faire pendant l’analyse du fichier.
3. **Administration.** Dans **Dossiers**, ouvrez la demande et choisissez **Envoyer à l’épicerie**. L’épicier confirme la dette et son montant, ou signale un désaccord avec un motif.
4. **Administration.** Après la confirmation de la dette, choisissez **Approuver**, puis **Publier**. Si un dossier est signalé, traitez le motif avant de lever le signalement. Vérifiez les doublons si la même référence de dette est déjà approuvée pour cette épicerie.

### De la promesse WhatsApp au paiement confirmé

1. **Donateur.** Ouvrez un dossier publié, accédez à **Contribution**, saisissez un montant dans la limite du solde et envoyez le message prérempli dans WhatsApp.
2. Revenez sur la plateforme et cliquez sur **J’ai envoyé le message · Enregistrer ma promesse**. Dans **Historique des dons**, ouvrez le détail de la contribution et communiquez sa référence à l’épicier. Le solde reste inchangé à ce stade.
3. **Épicier.** Une fois le paiement reçu hors plateforme, allez dans **Mon espace → Règlements → Paiements reçus hors plateforme**. Retrouvez la promesse, indiquez le montant reçu et la référence du paiement, joignez le justificatif et cliquez sur **Confirmer le paiement reçu**. Pour une nouvelle déclaration, le solde baisse immédiatement après confirmation, sans validation administrative.
4. **Donateur.** Dès que l’épicier confirme la réception, la plateforme affiche **« Réception du paiement confirmée »** et le montant effectivement reçu dans **Historique des dons → Détail de la contribution**. Si la page est déjà ouverte, actualisez-la. Si tout le montant dû a été payé, le dossier passe à **Réglé**. Un dossier **Financé** peut encore être en attente de règlement à l’épicerie. Les deux états sont accessibles via le filtre **État du financement** dans **Dossiers publiés**.

Le bouton de contribution sur la fiche publique est actuellement conditionné à l’activation du simulateur. Si ce bouton est masqué, un donateur connecté peut ouvrir le formulaire correspondant via `/donations/new?case=<référence-publique>`. Cette fonctionnalité enregistre les promesses et les paiements confirmés par les épiciers. Elle ne traite aucun paiement en ligne.

### Fonds des dettes urgentes

Le fonds urgent utilise un paiement simulé dans les environnements où ce mode est activé. Après confirmation simulée, le moteur répartit le montant entre les dossiers éligibles selon leur priorité et leur solde. La priorité combine la vulnérabilité du foyer (35 %), l’ancienneté de la dette (25 %), le risque de perdre l’accès aux produits de base (25 %) et le montant restant (15 %). Le solde non affecté après 30 jours est mis en attente de remboursement. Les tableaux financiers distinguent les dons simulés des confirmations de paiements reçus hors plateforme.

Pour lancer le projet, consultez les [instructions d’installation](#local-setup). Les commandes et noms de variables sont identiques dans les deux langues de l’application.

## English guide

Ktab-L7anout is a Moroccan food-solidarity platform available in French and Arabic. It helps families settle verified grocery debts through case-specific WhatsApp pledges and shop-confirmed payments. It also provides an urgent debt pool for simulated funding and public financial transparency.

## Contents

- [How the platform works](#how-the-platform-works)
- [Local setup](#local-setup) · [Environment reference](#environment-reference)
- [Document privacy and donor downloads](#document-privacy-and-donor-downloads)
- [Funding and financial reporting](#funding-and-financial-reporting)
- [Routes and technical reference](#routes-and-technical-reference)
- [Verify](#verify) · [Project structure](#project-structure)
- [Deployment preparation](#deployment-preparation) · [Troubleshooting](#troubleshooting)
- [Further documentation](#further-documentation)

## How the platform works

Ktab-L7anout keeps case review and money movement separate. A beneficiary applies for assistance, the assigned shop confirms the debt, and an administrator approves and publishes the case. A donor chooses a published case or the urgent pool. The verified shop is the intended recipient of settlement.

```mermaid
flowchart LR
    A[Beneficiary saves draft and attaches evidence] --> B[Beneficiary submits with consent]
    B --> C[Administrator routes to shop]
    C --> D[Shopkeeper confirms debt]
    D --> E[Administrator approves and publishes]
    E --> F[Donor sends a WhatsApp message and records a pledge]
    F --> G[Shopkeeper confirms receipt and attaches proof]
    G --> H[Confirmed amount reduces the case balance]
    E --> I[Donor contributes to the urgent pool in a test environment]
    I --> J[Confirmed simulated payment funds eligible debts]
    J --> K[Administrators and shop reconcile simulated settlement]
```

### Role guide

| Role | Where to start | What you can do |
| --- | --- | --- |
| Visitor | [Dossiers publiés](http://127.0.0.1:5173/cases) | Browse anonymized published cases, filter by region, city, verified debt, funding state, or urgency, and view financial transparency. |
| Beneficiary | [Mon espace](http://127.0.0.1:5173/dashboard/beneficiary) | Prepare a draft, select a shop, attach documents, accept consent, submit, and track progress. |
| Shopkeeper | [Mes épiceries](http://127.0.0.1:5173/dashboard/shopkeeper) | Register a shop, upload evidence, confirm or dispute assigned debts, confirm payments received outside the platform, and respond to simulated settlements. |
| Donor | [Historique des dons](http://127.0.0.1:5173/donations) | Send a WhatsApp pledge for a published case, track shop-confirmed receipt, or test urgent-pool contributions and view allocations and returns. |
| Administrator | [Centre d’administration](http://127.0.0.1:5173/dashboard/administrator) | Verify shops, review and publish cases, manage accounts, inspect external payment evidence and audit logs, reconcile simulated settlements, and export reports. |

Public registration requires a phone number. Existing users can add it in **Mon profil**. The account owner and authorized administrators can see it. A user can opt in to WhatsApp contact, which shows a contact link in the administrator’s **Utilisateurs** list. Opening the link starts a chat in WhatsApp. The application does not send a message automatically.

### Publish a case and record a WhatsApp contribution

1. **Épicier** — Register the shop and upload an original document. An administrator opens [Épiceries](http://127.0.0.1:5173/dashboard/administrator/shops), selects the shop, and chooses **Vérifier → Enregistrer** when the shop meets the evidence requirements.
2. **Bénéficiaire** — Click **Nouvelle demande**, select the city and shop, enter the debt details, and click **Enregistrer le brouillon**. The amount in the beneficiary form is entered in dirhams. Enter `10` for 10 MAD or `12,50` for 12.50 MAD. The API stores these as `1000` and `1250` minor units.
3. Attach the supporting file, accept the consent terms, and click **Soumettre**. You can draft a request for a pending shop, but submission requires an approved shop. A current attachment in quarantine is sufficient to submit.
4. **Administrateur** — Open the request in [Dossiers](http://127.0.0.1:5173/dashboard/administrator/cases) and choose **Envoyer à l’épicerie**.
5. **Épicier** — Open the assigned request and choose **Confirmer la dette** if the amount is correct. Dispute it with a reason if it is incorrect.
6. **Administrateur** — Reopen the confirmed case, choose **Approuver**, then **Publier**. **Signaler** flags a case for review and blocks approval and publication. Use **Lever le signalement** only after resolving the issue.
7. **Donateur** — Open the published case and choose **Contribution**. Enter an amount in MAD up to the remaining balance, open WhatsApp, and send the prefilled message to the shop. Return to the application and choose **J’ai envoyé le message · Enregistrer ma promesse**. Share the contribution reference from the donation detail so the shop can match the payment to the pledge. The pledge leaves the balance unchanged.
8. **Épicier** — Once payment has been received outside the platform, go to **Mon espace → Règlements → Paiements reçus hors plateforme**. Select the pledge, enter the amount actually received and the payment reference, attach a PDF or image, then click **Confirmer le paiement reçu**. A successful confirmation reduces the balance immediately, with no administrative approval step. A fully paid case becomes **Réglé**.
9. **Donor** — After the shopkeeper confirms receipt, the platform shows **« Réception du paiement confirmée »** (payment receipt confirmed) and the amount actually received in the contribution details under **Historique des dons**. Refresh the page if it is already open. Use **Dossiers publiés → État du financement** to find funded or settled cases. If a case is missing, check its publication status and clear any filters that exclude it.

The public case page currently shows the **Contribution** button only when the simulator is enabled. If the button is hidden, a signed-in donor can open the case-specific form at `/donations/new?case=<public-reference>`. The WhatsApp workflow records pledges and shop-confirmed external receipts. It does not process online payments.

**Territoires** counts applications that are no longer drafts. It compares regions and shows verified shop coverage by city. The beneficiary can track a saved draft before it appears in the regional totals.

## Local setup

### Prerequisites

- PHP 8.5 with the Laravel extensions and `pdo_pgsql` enabled. Enable `pdo_sqlite` for the default in-memory test configuration.
- Composer 2, Node.js 24 with npm, and a running PostgreSQL instance.
- On Windows, find the CLI configuration with `php --ini`, enable `extension=pdo_pgsql`, and confirm with `php -m`. Use `npm.cmd` in PowerShell if execution policy blocks the npm script shim.

### Database setup

Connect as a PostgreSQL administrator using `psql -h 127.0.0.1 -U postgres -d postgres`, then create a local application role and an owned database. The password command prompts privately.

```sql
CREATE ROLE morocco_app LOGIN;
\password morocco_app
CREATE DATABASE morocco_debt_relief OWNER morocco_app;
\q
```

### Start the backend

From the repository root in one terminal:

```powershell
cd backend
composer install
if (!(Test-Path .env)) { Copy-Item .env.example .env }
```

Set `DB_PASSWORD` in `backend/.env` to the password chosen above. Adjust the other PostgreSQL values if your local setup differs. Generate the key on a fresh installation with an empty APP_KEY, then apply migrations. Preserve the existing key on subsequent runs.

```powershell
php artisan key:generate
php artisan migrate
php artisan db:seed --class=MoroccoGeographySeeder
php artisan serve --host=127.0.0.1 --port=8000
```

The liveness endpoint is `GET http://127.0.0.1:8000/api/health`. It returns `{"data":{"status":"ok"}}`. It checks the HTTP application process.

### Email and administrator setup

Run a local SMTP inbox such as Mailpit before testing registration, verification, and password reset. The example uses SMTP at `127.0.0.1:1025`. Mailpit normally exposes its inbox at `http://127.0.0.1:8025`. If upgrading an earlier checkout, set `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`, and `FRONTEND_URL=http://127.0.0.1:5173` in the existing backend environment. After applying the Phase 2 migrations, sign in again to obtain a versioned session.

For local development without SMTP, set `MAIL_MAILER=local_spool`. Open verification messages from `backend/storage/app/private/mail` in an email client. Messages contain signed links, so keep the directory private and remove old copies when they are no longer needed.

Run `php artisan queue:work` from `backend/` in a separate terminal to deliver password reset emails.

Public registration offers donor, beneficiary, and shopkeeper accounts. Provision a new administrator only from a trusted backend console.

```powershell
php artisan accounts:create-administrator operator@example.test
```

Enter the name and password at the prompts. The password is read without echo. Local administrators can sign in immediately. Staging and production administrators must verify their email first. Use an address you control in a deployed environment.

### Shop evidence and geography

See the [official geography sources and coverage](docs/geography-data.md). Shop evidence enters private quarantine. To make clean evidence available for review, install ClamAV, update its signatures, set `SHOP_CLAMSCAN_BINARY` to the absolute path of `clamscan`, and run `php artisan shops:scan-documents` from `backend/`. Repeat the command as needed because it processes up to 100 files per run.

For beneficiary and shop evidence on debt applications, run `php artisan shops:scan-documents --cases`. A beneficiary may submit with an attachment that is still awaiting scanning. Reviewers route it to the shop and can publish only after the assigned shop owner confirms the amount and an administrator approves it.

The public discovery pages are `/cases` and `/urgent`. Reviewers can set an anonymized story and urgency grades from the administrator case dashboard. Configure the urgency weights, normalization caps, and threshold in `backend/config/discovery.php`.

### Simulated donations

Use a dedicated local PostgreSQL database and apply migrations. Set `FUNDING_SIMULATOR_ENABLED=true` in `backend/.env` while running with `APP_ENV=local`. Sign in as a donor to open `/donations/new`. The checkout uses simulated outcomes and collects no payment details. Use a random simulator secret of at least 32 characters for signed webhook testing.

Run `php artisan funding:process` to retry waiting receipts and confirm queued simulated refunds. For recurring processing, run `php artisan schedule:work` separately. Simulation remains disabled in production and its totals are labeled and separated from live reporting. Shop settlements are also simulated.

To run the two-process PostgreSQL concurrency test, create a separate disposable database whose name starts with `mdr_funding_concurrency_`, migrate it, and set `FUNDING_CONCURRENCY_DATABASE` to that name before running PHPUnit. It uses committed fixtures and retains them because the ledger protects financial history from deletion.

### Start the frontend

From the repository root in another terminal:

```powershell
cd frontend
npm.cmd ci
if (!(Test-Path .env)) { Copy-Item .env.example .env }
npm.cmd run dev
```

Open [Ktab-L7anout](http://127.0.0.1:5173). Use `127.0.0.1` consistently for both services so the development origins and Sanctum cookies work. On POSIX shells, use `cp` and `npm` in place of `Copy-Item` and `npm.cmd`.

## Environment reference

| Setting | Local use |
| --- | --- |
| `APP_ENV` and `APP_DEBUG` | Use `local` and `false` with the default local setup. |
| `APP_URL` and `FRONTEND_URL` | `http://127.0.0.1:8000` and `http://127.0.0.1:5173`. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT` | `pgsql`, `127.0.0.1`, and `5432`. |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Use the credentials for the local application database. |
| `VITE_API_URL` | Set it to `http://127.0.0.1:8000` locally. Restart Vite after changing it. Use an empty value for a same-origin production build. |
| `SANCTUM_STATEFUL_DOMAINS` and `FRONTEND_ORIGINS` | `127.0.0.1:5173` and `http://127.0.0.1:5173`. Use the same hostname throughout. |
| `SESSION_DRIVER` and `SESSION_SECURE_COOKIE` | `database` and `false` for local HTTP. Use secure cookies with HTTPS in deployed environments. |
| `MAIL_MAILER` | `local_spool` for development mail files or `smtp` for a configured inbox. |
| `FUNDING_SIMULATOR_ENABLED` | Set `true` to test checkout and settlements. Production rejects simulation. |
| `FUNDING_SIMULATOR_SECRET` | A separate random secret of at least 32 characters for signed callback testing. Browser checkout does not need it. |
| `PRIVATE_STORAGE_ROOT` | Optional private directory override. The default is `backend/storage/app/private`. |
| `SHOP_CLAMSCAN_BINARY` | Absolute path to the scanner executable. |
| `SHOP_LOCAL_MANUAL_EVIDENCE_REVIEW` and `CASE_LOCAL_MANUAL_EVIDENCE_REVIEW` | Local-only options for manual review of unscanned synthetic documents. Both default to `false`. |

Keep existing `.env` files and the application key when updating your local checkout. Never commit credentials, private documents, or captured emails. If Laravel configuration was cached, run `php artisan config:clear` after changing it. Restart running queue workers after configuration changes. If PostgreSQL works only with `php -d extension=pdo_pgsql`, enable the extension in the PHP CLI configuration before starting tools that spawn PHP child processes.

## Document privacy and donor downloads

Evidence accepts PDF, JPG, or PNG up to 5 MiB and stays on the private disk. Each download is scoped to the signed-in role and record. A beneficiary can submit a case while its attachment is awaiting scanning. In staging and production, the file itself remains unavailable for download while quarantined. Rejected files do not satisfy submission requirements.

The shop original is visible only to its authorized owners and administrators when the evidence controls allow it. To make a donor copy, an administrator uploads a separate anonymized file, waits for its scan, then selects **Partager avec les donateurs**. Remove names, signatures, contact details, and hidden metadata before sharing. Donors can download shared copies from the associated published case. A revoked copy or an ineligible case is no longer available to donors.

## Funding and financial reporting

A WhatsApp pledge does not change the debt balance. A shop-confirmed external payment reduces the case balance and is tracked separately from platform receipts, payouts, and financial totals. A successful simulator confirmation records a receipt and ledger event. Specific-case allocations stay restricted to the designated case, with excess queued for simulated return. An urgent-pool receipt can be allocated across several ranked cases. Unallocated pool funds are held for up to 30 days from confirmation.

Urgency combines household vulnerability (35%), debt age (25%), risk of losing access to basic groceries (25%), and remaining capacity (15%). The default urgent threshold is 60/100 for an eligible open case with current assessments. See [business rules](docs/business-rules.md) for the scoring rubric and tie-breaking policy.

The transparency dashboard separates confirmed receipts, allocations, recorded transfers, shop-confirmed receipts, and reconciled settlements. Administrator CSV exports provide the journal and per-receipt balances. Financial adjustments preserve prior events. The app collects no payment card details in simulation.

## Routes and technical reference

| Area | Main routes |
| --- | --- |
| Public | `/`, `/cases`, `/cases/{reference}`, `/urgent`, `/transparency` |
| Account | `/register`, `/login`, `/forgot-password`, `/reset-password` |
| Role dashboards | `/dashboard/beneficiary`, `/dashboard/shopkeeper`, `/dashboard/donor`, `/dashboard/administrator` |
| Donations | `/donations`, `/donations/new` and donation details or checkout |
| API examples | `/api/health`, `/api/v1/regions`, `/api/v1/cases`, `/api/v1/discovery`, `/api/v1/transparency`, signed-in `/api/v1/me` |

The frontend uses React, TypeScript, Vite, Tailwind CSS, React Router, Axios, React Hook Form, and Zod. The backend uses Laravel, Sanctum, PostgreSQL, Form Requests, Policies, and API Resources. The browser uses session cookies and obtains a CSRF cookie from `/sanctum/csrf-cookie` before protected mutations. The public API exposes anonymized published cases and aggregate statistics.

## Verify

From `backend/` after generating the application key:

```powershell
php artisan test
php vendor/bin/pint --test
composer validate --strict
composer audit
```

The standard tests use in-memory SQLite. For PostgreSQL coverage and the multi-process funding test, create two disposable databases as the local PostgreSQL administrator:

```sql
CREATE DATABASE morocco_debt_relief_test OWNER morocco_app;
CREATE DATABASE mdr_funding_concurrency_local OWNER morocco_app;
```

From `backend/`, clear any cached configuration and run:

```powershell
php artisan config:clear
$env:DB_CONNECTION = 'pgsql'
$env:DB_DATABASE = 'mdr_funding_concurrency_local'
php artisan migrate
$env:FUNDING_CONCURRENCY_DATABASE = 'mdr_funding_concurrency_local'
$env:DB_DATABASE = 'morocco_debt_relief_test'
php vendor/bin/phpunit --colors=never
Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE, Env:FUNDING_CONCURRENCY_DATABASE
```

The main suite refreshes the test schema. The concurrency test retains its committed synthetic fixtures in the separate concurrency database. Run only one suite against each pair at a time.

From `frontend/`:

```powershell
npm.cmd test
npm.cmd run lint
npm.cmd run build
npm.cmd audit
```

## Project structure

- `backend/app/Http/` owns routing behavior, request validation, JSON resources, and request locale selection. `backend/app/Models/` and `backend/app/Services/` own account records and transactional registration.
- `backend/config/`, `backend/routes/`, `backend/lang/`, and `backend/database/` hold service configuration, routes, response translations, factories, and migrations.
- `frontend/src/app/` contains routing, layout, and locale catalogs. `frontend/src/features/` contains identity, public case discovery, applications, shop management, donations, settlements, and administration.
- `frontend/src/shared/` contains Axios and reusable UI primitives. The homepage photograph is generated illustration.
- `backend/tests/` and frontend feature tests cover the main workflows. The lockfiles pin dependencies.

See the [architecture](docs/architecture.md), [API contract](docs/api-documentation.md), and [deployment notes](docs/deployment.md).

### Simulated shop settlements

With the simulator enabled and migrations applied, fully fund a published case and open the administrator dashboard. Prepare the settlement, then use a different authorized administrator to approve and submit it. Record the simulated transfer, confirm it as the assigned shopkeeper, then reconcile it as an administrator who did not initiate it. Use the administrator dashboard to inspect returns, journal events, and reconciliation shortages, or export the two CSV reports. Open the public `/transparency` page to view aggregate simulation results.

### Administrator workspace

Authorized administrators can open `/dashboard/administrator` for overview, cases, users, shops, finances, regional activity, and audit history. Apply pending Laravel migrations before opening the workspace. Financial search, manual returns, and settlement controls use the local simulator.

## Deployment preparation

Use the [deployment runbook](docs/deployment.md) and [backup and recovery procedure](docs/backup-and-recovery.md). The reference deployment builds Vite for one HTTPS origin and runs Laravel behind PHP-FPM with PostgreSQL, private evidence storage, a supervised queue and a scheduler. Run the staging rehearsals before any authorized launch.

Before a production release, verify TLS, private storage, database grants, mail, scheduling, monitoring, and a coordinated database and evidence restore. Live donations depend on approved legal and payment-provider arrangements, authenticated callbacks, tested refunds, and operational reconciliation. Use simulated payments in isolated staging.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| The website loads but registration fails | Verify `VITE_API_URL` and restart Vite. The frontend and API should both use `127.0.0.1`. |
| The health page works but API data fails to load | Check PostgreSQL, migrations, PDO drivers, and credentials. Request `/api/v1/regions` to check database-backed data. |
| Administrator setup says the address is in use | Use an address that does not already belong to a public account. Enter matching passwords of at least 12 characters. |
| Registration or reset email is missing | Open the configured local mailbox. For password recovery, start the queue worker. |
| A beneficiary cannot find an épicerie | Select the shop’s city. The list includes eligible pending and approved shops. |
| A donor cannot find a case | Check that it was submitted, shop-confirmed, approved, and published. Clear any flag after review, and reset excluding filters. |
| A download button is disabled | Check whether the file is scanned, accessible for the role, and shared if the viewer is a donor. |
| Checkout or settlements are unavailable | Check `FUNDING_SIMULATOR_ENABLED`, the account role, and case eligibility or funding status. |

## Further documentation

- [Architecture](docs/architecture.md), [database design](docs/database-design.md), [business rules](docs/business-rules.md), and [API contract](docs/api-documentation.md)
- [Geography data](docs/geography-data.md) and [development roadmap](docs/development-roadmap.md)
- [Security design](docs/security.md), [security audit](docs/security-audit-report.md), and [financial integrity report](docs/financial-integrity-report.md)
- [QA report](docs/qa-report.md), [deployment and rollback](docs/deployment.md), and [backup and recovery](docs/backup-and-recovery.md)

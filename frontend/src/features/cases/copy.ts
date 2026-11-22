export const caseCopy = {
  fr: {
    publicTitle: 'Dossiers de dette publiés', publicLink: 'Dossiers publiés', publicEmpty: 'Aucun dossier publié pour le moment.',
    publicAmount: 'Montant approuvé en centimes',
    owner: 'Mes demandes d’aide', shop: 'Demandes à vérifier', admin: 'Examen des demandes',
    new: 'Nouvelle demande', choose: 'Choisir un dossier', back: 'Toutes les demandes', save: 'Enregistrer le brouillon',
    shopName: 'Épicerie', reference: 'Référence de la dette', date: 'Date de la dette', amount: 'Montant en centimes (MAD)',
    situation: 'Situation financière', household: 'Nombre de personnes dans le foyer (facultatif)',
    consent: 'J’accepte que mes informations et justificatifs servent à examiner ma demande. L’épicerie reçoit uniquement les détails nécessaires pour confirmer la dette. Un dossier publié contient uniquement la région, le montant approuvé et une référence publique.',
    submit: 'Soumettre', cancel: 'Annuler la demande', file: 'Justificatif (PDF, JPEG ou PNG)', upload: 'Envoyer le fichier',
    evidence: 'Justificatifs', refresh: 'Actualiser', history: 'Historique', reason: 'Motif', duplicates: 'Doublons possibles',
    flagged: 'Signalé pour examen', none: 'Aucune demande.', guidance: 'Un justificatif analysé est nécessaire avant la soumission.',
    actions: {
      submit: 'Demande soumise', cancel: 'Demande annulée', route: 'Envoyer à l’épicerie', confirm: 'Confirmer la dette',
      dispute: 'Contester le montant', reject: 'Rejeter', approve: 'Approuver', publish: 'Publier',
      request_info: 'Demander des informations', flag: 'Signaler', clear_flag: 'Lever le signalement',
      unpublish: 'Dépublier', suspend: 'Suspendre la campagne', reopen_dispute: 'Rouvrir la vérification',
    },
    statuses: {
      draft: 'Brouillon', submitted: 'Soumise', pending_verification: 'Vérification par l’épicerie', under_review: 'En cours d’examen',
      approved: 'Approuvée', published: 'Publiée', rejected: 'Rejetée', disputed: 'Contestée', cancelled: 'Annulée',
      quarantined: 'En cours d’analyse', available: 'Disponible',
    },
  },
  ar: {
    publicTitle: 'ملفات الديون المنشورة', publicLink: 'الملفات المنشورة', publicEmpty: 'لا توجد ملفات منشورة حالياً.',
    publicAmount: 'المبلغ المعتمد بالسنتيم',
    owner: 'طلبات المساعدة الخاصة بي', shop: 'طلبات التحقق', admin: 'مراجعة الطلبات',
    new: 'طلب جديد', choose: 'اختر ملفاً', back: 'جميع الطلبات', save: 'حفظ المسودة',
    shopName: 'البقالة', reference: 'مرجع الدين', date: 'تاريخ الدين', amount: 'المبلغ بالسنتيم (درهم)',
    situation: 'الوضع المالي', household: 'عدد أفراد الأسرة (اختياري)',
    consent: 'أوافق على استخدام بياناتي ومستنداتي لدراسة طلبي. يتلقى البقال فقط المعلومات اللازمة لتأكيد الدين. يتضمن الطلب المنشور الجهة والمبلغ المعتمد ومرجعاً عاماً فقط.',
    submit: 'إرسال الطلب', cancel: 'إلغاء الطلب', file: 'مستند داعم (PDF أو JPEG أو PNG)', upload: 'إرسال الملف',
    evidence: 'المستندات', refresh: 'تحديث', history: 'السجل', reason: 'السبب', duplicates: 'طلبات مكررة محتملة',
    flagged: 'تم وضع علامة للمراجعة', none: 'لا توجد طلبات.', guidance: 'يلزم مستند مفحوص قبل إرسال الطلب.',
    actions: {
      submit: 'تم إرسال الطلب', cancel: 'أُلغي الطلب', route: 'إرسال للبقال', confirm: 'تأكيد الدين',
      dispute: 'الاعتراض على المبلغ', reject: 'رفض', approve: 'اعتماد', publish: 'نشر',
      request_info: 'طلب معلومات إضافية', flag: 'وضع علامة', clear_flag: 'إزالة العلامة',
      unpublish: 'إلغاء النشر', suspend: 'تعليق الحملة', reopen_dispute: 'إعادة فتح التحقق',
    },
    statuses: {
      draft: 'مسودة', submitted: 'مرسل', pending_verification: 'قيد تحقق البقال', under_review: 'قيد المراجعة',
      approved: 'معتمد', published: 'منشور', rejected: 'مرفوض', disputed: 'متنازع عليه', cancelled: 'ملغى',
      quarantined: 'قيد الفحص', available: 'متاح',
    },
  },
}

export type Action = keyof typeof caseCopy.fr.actions
export type CaseStatus = Exclude<keyof typeof caseCopy.fr.statuses, 'quarantined' | 'available'>

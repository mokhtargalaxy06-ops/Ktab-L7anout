const copy = {
  fr: {
    phone: 'Numéro de téléphone', phoneHint: 'Ex. 06 12 34 56 78 ou +212 612 345 678',
    phoneInvalid: 'Saisissez un numéro marocain ou un numéro avec son indicatif international.',
    whatsappConsent: 'J’autorise les administrateurs à me contacter sur WhatsApp.',
    whatsapp: 'Contacter sur WhatsApp',
    role: 'Type de compte', donor: 'Donateur', beneficiary: 'Bénéficiaire', shopkeeper: 'Épicier', administrator: 'Administrateur',
    dashboard: 'Mon espace', profile: 'Mon profil', save: 'Enregistrer', saved: 'Profil enregistré.',
    verify: 'Vérifiez votre adresse e-mail pour accéder aux fonctions de votre espace.',
    resend: 'Renvoyer le lien', sent: 'Consultez votre boîte de réception.', verified: 'Votre adresse e-mail est vérifiée.',
    forgot: 'Mot de passe oublié ?', reset: 'Réinitialiser le mot de passe', send: 'Envoyer le lien',
    recoveryNotice: 'Si un compte actif correspond à cette adresse, vous recevrez un lien.',
    invalidLink: 'Ce lien est invalide ou expiré. Demandez un nouveau lien.',
    resetDone: 'Votre mot de passe est modifié.', forbidden: 'Vous n’avez pas accès à cet espace.',
    sections: {
      donor: ['Dossiers publics', 'Historique des dons'],
      beneficiary: ['Demandes d’aide', 'Justificatifs et suivi'],
      shopkeeper: ['Profil de l’épicerie', 'Vérifications et règlements'],
      administrator: ['Comptes et demandes', 'Dossiers et suivi financier'],
    },
    pending: 'Fonctions prévues pour une prochaine phase.',
  },
  ar: {
    phone: 'رقم الهاتف', phoneHint: 'مثال: ‎06 12 34 56 78 أو ‎+212 612 345 678',
    phoneInvalid: 'أدخل رقماً مغربياً أو رقماً مع رمز الاتصال الدولي.',
    whatsappConsent: 'أوافق على أن يتواصل معي المسؤولون عبر واتساب.',
    whatsapp: 'التواصل عبر واتساب',
    role: 'نوع الحساب', donor: 'متبرع', beneficiary: 'مستفيد', shopkeeper: 'بقال', administrator: 'مسؤول',
    dashboard: 'مساحتي', profile: 'ملفي الشخصي', save: 'حفظ', saved: 'تم حفظ الملف الشخصي.',
    verify: 'تحقق من عنوان بريدك الإلكتروني للوصول إلى وظائف مساحتك.',
    resend: 'إعادة إرسال الرابط', sent: 'تحقق من صندوق بريدك.', verified: 'تم التحقق من عنوان بريدك الإلكتروني.',
    forgot: 'نسيت كلمة المرور؟', reset: 'إعادة تعيين كلمة المرور', send: 'إرسال الرابط',
    recoveryNotice: 'إذا كان العنوان مرتبطاً بحساب نشط فسيصلك رابط.',
    invalidLink: 'الرابط غير صالح أو منتهي الصلاحية. اطلب رابطاً جديداً.',
    resetDone: 'تم تغيير كلمة مرورك.', forbidden: 'لا يمكنك الوصول إلى هذه المساحة.',
    sections: {
      donor: ['الملفات المنشورة', 'سجل التبرعات'],
      beneficiary: ['طلبات الدعم', 'المستندات والمتابعة'],
      shopkeeper: ['ملف المتجر', 'التحقق والتسويات'],
      administrator: ['الحسابات والطلبات', 'الملفات والمتابعة المالية'],
    },
    pending: 'هذه الوظائف مقررة في مرحلة لاحقة.',
  },
}

export type AccountRole = keyof typeof copy.fr.sections
export const publicRoles = ['donor', 'beneficiary', 'shopkeeper'] as const

export function accountDashboard(roles: string[]) {
  const role = ['administrator', ...publicRoles].find(value => roles.includes(value))
  return role ? '/dashboard/' + role : '/'
}

export default copy

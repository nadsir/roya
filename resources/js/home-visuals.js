// Existing Pexels photography, used only for non-product Home artwork.
// Source pages: /photo/a-woman-posing-in-a-trendy-outfit-while-holding-a-bag-6211618/
// /photo/a-woman-in-a-beige-set-with-a-handbag-22890201/
// /photo/woman-in-beige-blouse-skirt-and-sunglasses-holds-handbag-20167616/
export const homeVisuals = [
    { src: '/images/slider/slide-01.png', alt: 'استایل زنانه با بلوز روشن، دامن پلیسه بژ، کیف مشکی و نیم‌بوت' },
    { src: '/images/slider/slide-02.png', alt: 'استایل شهری زنانه با لباس بژ، کیف دستی روشن و عینک آفتابی' },
    { src: '/images/slider/slide-03.png', alt: 'عکس مد زنانه با لباس بژ و کیف دوشی در نور گرم استودیو' },
];

export function homeCategoryVisual(category) {
    const label = `${category.slug} ${category.name}`;
    if (/bag|کیف/i.test(label)) return homeVisuals[1];
    if (/accessor|jewel|اکسسوری|زیور/i.test(label)) return homeVisuals[2];
    return homeVisuals[0];
}

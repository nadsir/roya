// ROYA Stories — central, editable content file for the Home stories rail.
// Edit titles, covers, posters and video lists here only; the HomeStories
// component reads this file and renders whatever is written below.
// Order matters: stories render in array order, and each story plays its
// videos in the order they appear in its own `videos` array.

export const stories = [
    {
        id: 'style-today',
        title: 'استایل امروز',
        alt: 'استایل زنانه با دامن شیک و بلوز روشن',
        cover: 'https://royafashion.ir/storage/video/2.webp',
        poster: 'https://royafashion.ir/storage/video/2.webp',
        credit: 'Pete Bellis · Unsplash · CC0',
        film: 'Styling One Jumpsuit · Internet Archive',
        videos: [
            {
                id: 'style-today-video-01',
                title: 'Styling One Jumpsuit',
                src: 'https://royafashion.ir/storage/video/2.mp4',
            },
        
                        {
                id: 'style-today-video-01',
                title: 'Styling One Jumpsuit',
                src: 'https://archive.org/download/styling-one-jumpsuit-in-four-different-vibes/Styling%20One%20Jumpsuit%20in%20Four%20Different%20Vibes.mp4',
            },
        ],
    },

    {
        id: 'bags',
        title: 'کیف‌های ویژه',
        alt: 'کیف دستی سفید و آبی روی زمین',
        cover: 'https://royafashion.ir/storage/video/3.webp',
        poster: 'https://royafashion.ir/storage/video/3.webp',
        credit: 'Anna Hamilton · Unsplash · CC0',
        film: 'SBS Broadcasting · Commons · CC BY-SA 3.0',
        videos: [
            {
                id: 'bags-video-01',
                title: 'Amsterdam Fashion Week — Aftermovie',
                src: 'https://royafashion.ir/storage/video/3.mp4',
            },
        ],
    },

    {
        id: 'shoes',
        title: 'کفش‌ها',
        alt: 'چکمه‌های چرمی قهوه‌ای روی کف چوبی',
        cover: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/b/ba/Brown_leather_boots_on_a_wood_floor_%28Unsplash%29.jpg/500px-Brown_leather_boots_on_a_wood_floor_%28Unsplash%29.jpg',
        poster: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/b/ba/Brown_leather_boots_on_a_wood_floor_%28Unsplash%29.jpg/960px-Brown_leather_boots_on_a_wood_floor_%28Unsplash%29.jpg',
        credit: 'Radek Skrzypczak · Unsplash · CC0',
        film: 'Runway Fashion Model · Commons · CC BY 3.0',
        videos: [
            {
                id: 'shoes-video-01',
                title: 'Fashion Street Style',
                src: 'https://upload.wikimedia.org/wikipedia/commons/transcoded/d/d4/FASHION_STREET_STYLE_BY_V%C5%A8_%C4%90%C4%82NG_KHOA.webm/FASHION_STREET_STYLE_EDIT_V%C5%A8_%C4%90%C4%82NG_KHOA.webm.480p.vp9.webm',
            },
        ],
    },

    {
        id: 'trend',
        title: 'ترند',
        alt: 'استایل زمستانی زنانه با پالتوی خز مصنوعی',
        cover: 'https://royafashion.ir/storage/video/4.webp',
        poster: 'https://royafashion.ir/storage/video/4.webp',
        credit: 'Pete Bellis · Unsplash · CC0',
        film: '88 Gymnastic Heroes · Commons · CC BY 3.0',
        videos: [
            {
                id: 'trend-video-01',
                title: 'THYREN Fashion Film',
                src: 'https://royafashion.ir/storage/video/4.mp4',
            },
        ],
    },

    {
        id: 'roya-pick',
        title: 'پیشنهاد ROYA',
        alt: 'لباس مشکی کلاسیک زنانه',
        cover: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/e/e1/Little_Black_Dress_%28Unsplash%29.jpg/500px-Little_Black_Dress_%28Unsplash%29.jpg',
        poster: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/e/e1/Little_Black_Dress_%28Unsplash%29.jpg/960px-Little_Black_Dress_%28Unsplash%29.jpg',
        credit: 'Kira Ikonnikova · Unsplash · CC0',
        film: 'PRSSA ProFASHIONal Show · Internet Archive',
        videos: [
            {
                id: 'roya-pick-video-01',
                title: 'PRSSA ProFASHIONal Show',
                src: 'https://archive.org/download/autvdc-PRSSA_ProFASHIONal_Show/PRSSA_ProFASHIONal_Show.mp4',
            },
        ],
    },

    {
        id: 'accessories',
        title: 'اکسسوری‌ها',
        alt: 'زنی با عینک آفتابی در ژست عکاسی',
        cover: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/b/b8/Woman_posing_in_sunglasses_%28Unsplash%29.jpg/500px-Woman_posing_in_sunglasses_%28Unsplash%29.jpg',
        poster: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/b/b8/Woman_posing_in_sunglasses_%28Unsplash%29.jpg/960px-Woman_posing_in_sunglasses_%28Unsplash%29.jpg',
        credit: 'Matt Alaniz · Unsplash · CC0',
        film: 'Kurti Pants Styling · Internet Archive',
        videos: [
            {
                id: 'accessories-video-01',
                title: 'How To Wear Kurti Pants',
                src: 'https://archive.org/download/how-to-wear-kurti-pants-to-look-taller-slimmer/How%20to%20Wear%20Kurti%20Pants%20to%20Look%20Taller%20%26%20Slimmer.mp4',
            },
        ],
    },

    {
        id: 'newest',
        title: 'جدیدترین‌ها',
        alt: 'استایل خیابانی زنانه در نیویورک',
        cover: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/2/2f/New_York_Street_Fashion_%28Unsplash%29.jpg/500px-New_York_Street_Fashion_%28Unsplash%29.jpg',
        poster: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/2/2f/New_York_Street_Fashion_%28Unsplash%29.jpg/960px-New_York_Street_Fashion_%28Unsplash%29.jpg',
        credit: 'Yegide Matthews · Unsplash · CC0',
        film: 'Crystal Couture · Internet Archive',
        videos: [
            {
                id: 'newest-video-01',
                title: 'Crystal Couture',
                src: 'https://archive.org/download/fcpsva-Crystal_Couture_at_West_Potomac_Academy/Crystal_Couture_at_West_Potomac_Academy.mp4',
            },
                        {
                id: 'newest-video-01',
                title: 'Crystal Couture',
                src: 'https://archive.org/download/fcpsva-Crystal_Couture_at_West_Potomac_Academy/Crystal_Couture_at_West_Potomac_Academy.mp4',
            },
                        {
                id: 'newest-video-01',
                title: 'Crystal Couture',
                src: 'https://archive.org/download/fcpsva-Crystal_Couture_at_West_Potomac_Academy/Crystal_Couture_at_West_Potomac_Academy.mp4',
            },
        ],
    },
];

export default stories;

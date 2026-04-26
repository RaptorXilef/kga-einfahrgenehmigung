export function createHero(title, subtitle) {
    const section = document.createElement('section');
    section.className = 'hero';
    section.innerHTML = `
        <div class="hero__content">
            <h1 class="hero__title">${title}</h1>
            <p class="hero__subtitle">${subtitle}</p>
        </div>
    `;
    return section;
}

document.addEventListener('DOMContentLoaded', () => {
  const adminTrigger = document.querySelector('.admin-mini__trigger');
  const adminMini = document.querySelector('.admin-mini');
  const adminMiniClose = document.querySelector('.admin-mini__close');
  const assistantToggle = document.querySelector('.ai-assistant__toggle');
  const assistantPanel = document.getElementById('ai-panel');
  const assistantClose = document.querySelector('.ai-assistant__close');
  const aiForm = document.getElementById('ai-form');
  const aiInput = document.getElementById('ai-input');
  const aiMessages = document.getElementById('ai-messages');
  const quickButtons = document.querySelectorAll('.ai-quick-btn');
  const reviewForm = document.getElementById('review-form');
  const reviewsList = document.getElementById('reviews-list');

  const setAdminMiniOpen = (open) => {
    if (!adminMini) return;
    adminMini.hidden = !open;
  };

  if (adminTrigger) {
    adminTrigger.addEventListener('click', () => {
      if (!adminMini) return;
      adminMini.hidden = !adminMini.hidden;
    });
  }

  if (adminMiniClose) {
    adminMiniClose.addEventListener('click', () => setAdminMiniOpen(false));
  }

  const setAssistantOpen = (open) => {
    if (!assistantPanel || !assistantToggle) return;
    assistantPanel.hidden = !open;
    assistantToggle.setAttribute('aria-expanded', String(open));
  };

  if (assistantToggle) {
    assistantToggle.addEventListener('click', () => {
      const isOpen = !assistantPanel.hidden;
      setAssistantOpen(!isOpen);
    });
  }

  if (assistantClose) {
    assistantClose.addEventListener('click', () => setAssistantOpen(false));
  }

  const addMessage = (text, type = 'bot') => {
    if (!aiMessages) return;
    const bubble = document.createElement('div');
    bubble.className = `message message--${type}`;
    bubble.textContent = text;
    aiMessages.appendChild(bubble);
    aiMessages.scrollTop = aiMessages.scrollHeight;
  };

  const dayOrder = ['pon', 'tor', 'sre', 'cet', 'pet', 'sob', 'ned'];
  const dayNames = {
    pon: 'Ponedeljek',
    tor: 'Torek',
    sre: 'Sreda',
    cet: 'Četrtek',
    pet: 'Petek',
    sob: 'Sobota',
    ned: 'Nedelja'
  };

  const getBestAvailableSlot = () => {
    for (const dayKey of dayOrder) {
      const daySlots = availabilityMap[dayKey]?.free || [];
      if (daySlots.length > 0) {
        const slot = [...daySlots].sort((a, b) => a.localeCompare(b))[0];
        return `${dayNames[dayKey]} ob ${slot}`;
      }
    }
    return 'Najbližji termin je po dogovoru z nami. Prosimo, kontaktirajte nas.';
  };

  const getAiReply = async (input) => {
    try {
      const response = await fetch('/api/ai.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        body: JSON.stringify({ message: input })
      });

      const result = await response.json();
      if (!response.ok || result.status !== 'success') {
        throw new Error(result.message || 'AI pomočnik trenutno ni dosegljiv.');
      }

      return result.reply || 'Na kratko: lahko vam pomagam pri vprašanju o posojilu, postopku in terminih.';
    } catch (error) {
      return 'Lahko vam pomagam pri vprašanju o posojilu, postopku, znesku ali terminu. Če želite, nas lahko kontaktirate tudi na 041 473 133.';
    }
  };

  const buildRecommendationReply = (question) => {
    const text = (question || '').toLowerCase();
    if (text.includes('najboljši termin') || text.includes('predlagaj termin') || text.includes('kateri termin') || text.includes('najhitrejši termin')) {
      return `Najprimernejši termin je ${getBestAvailableSlot()}. Če želite, lahko tudi izberete ta termin v obrazcu za klic.`;
    }
    return null;
  };

  if (aiForm) {
    aiForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!aiInput) return;
      const value = aiInput.value.trim();
      if (!value) return;
      addMessage(value, 'user');
      aiInput.value = '';
      addMessage('Razmišljam...', 'bot');
      const recommendation = buildRecommendationReply(value);
      const reply = recommendation || await getAiReply(value);
      const botMessages = aiMessages.querySelectorAll('.message--bot');
      const lastBot = botMessages[botMessages.length - 1];
      if (lastBot && lastBot.textContent === 'Razmišljam...') {
        lastBot.textContent = reply;
      } else {
        addMessage(reply, 'bot');
      }
    });
  }

  quickButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      const text = button.textContent.trim();
      addMessage(text, 'user');
      addMessage('Razmišljam...', 'bot');
      const recommendation = buildRecommendationReply(text);
      const reply = recommendation || await getAiReply(text);
      const botMessages = aiMessages.querySelectorAll('.message--bot');
      const lastBot = botMessages[botMessages.length - 1];
      if (lastBot && lastBot.textContent === 'Razmišljam...') {
        lastBot.textContent = reply;
      } else {
        addMessage(reply, 'bot');
      }
    });
  });

  const form = document.getElementById('loan-form');
  const status = document.getElementById('form-status');
  const daySelect = document.getElementById('day-select');
  const timeSelect = document.getElementById('time-select');
  const availabilityGrid = document.getElementById('availability-grid');

  let availabilityMap = {
    pon: { free: ['10:00', '11:00', '12:00', '14:00', '17:00'], busy: ['09:00', '13:00', '15:00', '18:00'] },
    tor: { free: ['10:30', '11:30', '13:00', '16:00', '18:00'], busy: ['09:30', '12:00', '14:30', '17:30'] },
    sre: { free: ['09:00', '11:00', '13:30', '15:00', '18:00'], busy: ['10:00', '12:30', '14:00', '16:00'] },
    cet: { free: ['10:00', '12:00', '14:00', '15:30', '18:30'], busy: ['09:30', '11:30', '13:00', '17:00'] },
    pet: { free: ['09:30', '11:00', '13:00', '16:00', '19:00'], busy: ['10:30', '12:00', '14:30', '18:00'] },
    sob: { free: ['09:00', '10:30', '12:30', '15:00', '18:00'], busy: ['11:00', '13:00', '16:00', '17:30'] },
    ned: { free: ['09:30', '12:00', '14:00', '16:30', '19:00'], busy: ['10:00', '11:30', '13:30', '18:30'] }
  };

  const loadAvailability = async () => {
    try {
      const response = await fetch('/api/availability.php');
      if (!response.ok) return;
      const result = await response.json();
      if (result && typeof result === 'object') {
        availabilityMap = result;
      }
    } catch (error) {
      console.warn('Availability API unavailable, using fallback data.', error);
    }
  };

  const formatDayLabel = (key) => {
    const labels = {
      pon: 'Ponedeljek',
      tor: 'Torek',
      sre: 'Sreda',
      cet: 'Četrtek',
      pet: 'Petek',
      sob: 'Sobota',
      ned: 'Nedelja'
    };
    return labels[key] || 'Dan';
  };

  const renderAvailability = () => {
    if (!availabilityGrid || !daySelect) return;

    const dayKey = daySelect.value || 'pon';
    const slots = availabilityMap[dayKey] || { free: [], busy: [] };
    const entries = [
      ...slots.free.map((slot) => ({ value: slot, free: true })),
      ...slots.busy.map((slot) => ({ value: slot, free: false }))
    ].sort((a, b) => a.value.localeCompare(b.value));

    availabilityGrid.innerHTML = entries
      .map((entry) => {
        const className = entry.free ? 'slot slot--free' : 'slot slot--busy';
        const label = entry.free ? `${entry.value} • Prosto` : `${entry.value} • Zasedeno`;
        return `<button type="button" class="${className}" data-slot="${entry.value}" ${entry.free ? '' : 'disabled'}>${label}</button>`;
      })
      .join('');

    availabilityGrid.querySelectorAll('.slot--free').forEach((button) => {
      button.addEventListener('click', () => {
        if (!timeSelect) return;
        timeSelect.value = button.dataset.slot;
        availabilityGrid.querySelectorAll('.slot--free').forEach((slotButton) => slotButton.classList.toggle('is-selected', slotButton === button));
      });
    });
  };

  const renderTimeOptions = () => {
    if (!timeSelect || !daySelect) return;

    const dayKey = daySelect.value;
    if (!dayKey) {
      timeSelect.innerHTML = '<option value="">Izberi uro</option>';
      return;
    }

    const slots = availabilityMap[dayKey] || { free: [], busy: [] };
    const options = ['<option value="">Izberi uro</option>'];

    slots.free.forEach((slot) => {
      options.push(`<option value="${slot}">${slot}</option>`);
    });

    slots.busy.forEach((slot) => {
      options.push(`<option value="${slot}" disabled>${slot} • Zasedeno</option>`);
    });

    timeSelect.innerHTML = options.join('');
    if (timeSelect.value && !slots.free.includes(timeSelect.value) && !slots.busy.includes(timeSelect.value)) {
      timeSelect.value = '';
    }
  };

  const revealItems = document.querySelectorAll('.reveal');
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15 }
  );

  revealItems.forEach((item) => observer.observe(item));

  document.querySelectorAll('.select-shell').forEach((shell) => {
    shell.addEventListener('click', () => {
      shell.classList.add('active');
      setTimeout(() => shell.classList.remove('active'), 220);
    });
  });

  if (daySelect) {
    daySelect.addEventListener('change', () => {
      renderTimeOptions();
      renderAvailability();
    });
  }

  if (timeSelect) {
    timeSelect.addEventListener('change', () => {
      if (timeSelect.value) {
        const selectedSlot = timeSelect.value;
        availabilityGrid?.querySelectorAll('.slot--free').forEach((button) => {
          button.classList.toggle('is-selected', button.dataset.slot === selectedSlot);
        });
      }
    });
  }

  loadAvailability().finally(() => {
    renderTimeOptions();
    renderAvailability();
  });

  const defaultReviews = [
    { name: 'Luka', text: 'Zelo hitro in profesionalno. Odgovor so dali že isti dan.', anon: false },
    { name: 'Anonimno', text: 'Preprosto, hitro in brez nepotrebnih formalnosti. Hvala!', anon: true },
    { name: 'Maja', text: 'Super postopek in prijazno osebje. Priporočam.', anon: false }
  ];

  const reviewStorageKey = 'posvojilo-ljubljana-reviews';

  const loadReviews = () => {
    if (!reviewsList) return;

    const stored = localStorage.getItem(reviewStorageKey);
    const reviews = stored ? JSON.parse(stored) : defaultReviews;

    reviewsList.innerHTML = (Array.isArray(reviews) ? reviews : defaultReviews)
      .slice(0, 6)
      .map((review) => {
        const author = review.anon ? 'Anonimno' : (review.name || 'Stranka');
        return `
          <article class="review-item">
            <div class="review-item__head">
              <strong>${author}</strong>
              <span>Stranka</span>
            </div>
            <p>${(review.text || '').replace(/[<>]/g, '')}</p>
          </article>
        `;
      })
      .join('');
  };

  const saveReviews = (reviews) => {
    localStorage.setItem(reviewStorageKey, JSON.stringify(reviews));
  };

  if (reviewForm && reviewsList) {
    loadReviews();

    reviewForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const formData = new FormData(reviewForm);
      const name = String(formData.get('review_name') || '').trim();
      const isAnonymous = !!formData.get('anonymous');
      const text = String(formData.get('review_text') || '').trim();

      if (!text) return;

      const stored = JSON.parse(localStorage.getItem(reviewStorageKey) || '[]');
      const reviews = Array.isArray(stored) && stored.length ? stored : defaultReviews;
      reviews.unshift({
        name: isAnonymous ? 'Anonimno' : (name || 'Stranka'),
        text,
        anon: isAnonymous
      });

      saveReviews(reviews.slice(0, 6));
      loadReviews();
      reviewForm.reset();
    });
  }

  if (!form || !status) return;

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const formData = new FormData(form);

    if ((formData.get('website') || '').toString().trim() !== '') {
      status.textContent = 'Neveljaven zahtevek.';
      status.style.color = '#ff8869';
      status.classList.add('is-visible');
      return;
    }

    const dan = (formData.get('dan_klica') || '').toString().trim();
    const ura = (formData.get('ura_klica') || '').toString().trim();
    if (dan && ura) {
      formData.set('termin', `${formatDayLabel(dan)} ${ura}`);
    } else if (dan || ura) {
      formData.set('termin', `${dan || ''}${ura || ''}`.trim());
    }

    status.textContent = 'Oddajam vlogo...';
    status.style.color = '#f4d48b';
    status.classList.add('is-visible');

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
          Accept: 'application/json'
        }
      });

      const rawText = await response.text();
      let result = { status: 'error', message: 'Prišlo je do napake pri oddaji.' };

      if (rawText) {
        try {
          result = JSON.parse(rawText);
        } catch {
          if (rawText.includes('<html')) {
            result.message = 'PHP backend ni zagnan. Zaženi PHP server za oddajo obrazca.';
          } else {
            result.message = rawText.slice(0, 180) || 'Prišlo je do napake pri oddaji.';
          }
        }
      }

      if (!response.ok || result.status !== 'success') {
        throw new Error(result.message || 'Prišlo je do napake pri oddaji.');
      }

      status.textContent = 'Vloga uspešno oddana';
      status.style.color = '#7ef0b2';
      form.reset();
      renderTimeOptions();
      renderAvailability();
    } catch (error) {
      status.textContent = error.message || 'Prišlo je do napake pri oddaji.';
      status.style.color = '#ff8869';
    }
  });
});

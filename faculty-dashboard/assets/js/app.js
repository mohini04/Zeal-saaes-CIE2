// assets/js/app.js - Interactive Unit-wise & Sr No Filtering Scripts

document.addEventListener('DOMContentLoaded', () => {
  let currentUnit = 'all';
  let currentSubject = 'all';
  let currentType = 'all';

  const activityCards = document.querySelectorAll('.activity-card');

  function filterCards() {
    activityCards.forEach(card => {
      const cardUnit = card.getAttribute('data-unit') || 'Unit 1';
      const cardSubject = card.getAttribute('data-subject') || 'General';
      const cardType = card.getAttribute('data-type');

      const matchUnit = (currentUnit === 'all' || cardUnit === currentUnit);
      const matchSubject = (currentSubject === 'all' || cardSubject === currentSubject);
      const matchType = (currentType === 'all' || cardType === currentType);

      if (matchUnit && matchSubject && matchType) {
        card.style.display = 'flex';
      } else {
        card.style.display = 'none';
      }
    });
  }

  // Unit Filtering
  const unitPills = document.querySelectorAll('#unitFilterPills .pill');
  unitPills.forEach(pill => {
    pill.addEventListener('click', () => {
      unitPills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      currentUnit = pill.getAttribute('data-unit-filter');
      filterCards();
    });
  });

  // Subject Filtering
  const subjectPills = document.querySelectorAll('#subjectFilterPills .pill');
  subjectPills.forEach(pill => {
    pill.addEventListener('click', () => {
      subjectPills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      currentSubject = pill.getAttribute('data-subject-filter');
      filterCards();
    });
  });

  // Modal Open/Close Logic
  window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.style.display = 'flex';
    }
  };

  window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.style.display = 'none';
    }
  };

  // Close modal on background click
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        overlay.style.display = 'none';
      }
    });
  });
});

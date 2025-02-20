/**
 * Genererar en array med slumpmässiga heltal.
 *
 * @param {number} length - Antalet element i arrayen.
 * @param {number} max - Det maximala värdet för ett element.
 * @returns {number[]} En array med slumpmässiga heltal.
 */
function randomArray(length, max) {
  return Array.from({ length }, () => Math.floor(Math.random() * max));
}
/**
 * Rensar alla felmeddelanden från formuläret.
 */
function clearErrors() {
  const errors = document.querySelectorAll(".error");
  errors.forEach((error) => error.remove());
}
/**
 * Visar ett felmeddelande under ett specifikt inmatningsfält.
 *
 * @param {HTMLElement} input - Inmatningsfältet där felet uppstod.
 * @param {string} message - Felmeddelandet som ska visas.
 */
function showError(input, message) {
  const error = document.createElement("div");
  error.className = "error";
  error.textContent = message;
  input.after(error);
}
/**
 * Validerar en e-postadress.
 *
 * @param {string} email - E-postadressen som ska valideras.
 * @returns {boolean} True om e-postadressen är giltig, annars false.
 */
function validateEmail(email) {
  const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
  return emailRegex.test(email);
}
/**
 * Validerar ett användarnamn.
 *
 * @param {string} username - Användarnamnet som ska valideras.
 * @returns {boolean} True om användarnamnet är giltigt, annars false.
 */
function validateUsername(username) {
  const usernameRegex = /^[a-zA-Z0-9åäöÅÄÖ]{3,}$/;
  return usernameRegex.test(username);
}
/**
 * Validerar ett lösenord och dess bekräftelse.
 *
 * @param {string} password - Lösenordet som ska valideras.
 * @param {string} password2 - Bekräftelselösenordet som ska matchas.
 * @returns {boolean} True om lösenorden är giltiga och matchar, annars false.
 */
function validatePassword(password, password2) {
  return password.length >= 8 && password === password2;
}
/**
 * Validerar om användaren har godkänt sidans policy.
 *
 * @param {HTMLInputElement} policy - Checkbox-elementet för policyn.
 * @returns {boolean} True om policyn är godkänd, annars false.
 */
function validatePolicy(policy) {
  return policy.checked;
}

const output = document.getElementById("output");
const outputSorted = document.getElementById("output-sorted");
const array = randomArray(10, 100);
output.textContent = array.join(", ");
array.sort((a, b) => a - b);
outputSorted.textContent = array.join(", ");

const policyModal = document.getElementById("policy-modal");
const policyLink = document.getElementById("policy-link");
const policyLink2 = document.getElementById("policy-link2");
const policyClose = document.getElementById("policy-close");

/**
 * Lägg till eventlyssnare för att kunna stoppa formuläret och
 * validera fälten innan det skickas iväg
 */
document.getElementById('formjs').addEventListener('submit', function (event) {
  //Förhindra att formuläret skickas iväg innan mina kontroller körts
  event.preventDefault();

  //Hämta ut alla fält
  const email = event.target.querySelector('#email');
  const username = event.target.querySelector('#username');
  const password = event.target.querySelector('#password');
  const password2 = event.target.querySelector('#password2');
  const policy = event.target.querySelector('#policy');

  //Rensa alla felmeddelanden
  clearErrors();

  //Validera alla fält
  var valid = true;

  if (!validateEmail(email.value)) {
    valid = false;
    showError(email, "Ogiltig e-postadress");
  }

  if (!validateUsername(username.value)) {
    valid = false;
    showError(username, "Namn måste vara minst 3 tecken långt och kan innehålla bokstäver och siffror");
  }

  if (!validatePassword(password.value, password2.value)) {
    valid = false;
    showError(password, "Lösenordet måste vara minst 8 tecken långt och lösenorden måste matcha");
  }

  if (!validatePolicy(policy)) {
    valid = false;
    showError(policy, "Du måste godkänna villkoren");
  }

  /**
   * Om allt är ok, skicka formuläret
   * sparar också användarnamnet i sessionstorage
   * för att kunna hämta det på registreringssidan
   */
  if (valid) {
    sessionStorage.setItem("username", username.value);
    this.submit();
  }
});

/**
 * Lägg till eventlyssnare för att kunna stoppa även det andra
 * formuläret för att kunna spara användarnamnet i sessionstorage
 */
document.getElementById('formhtml5').addEventListener('submit', function (event) {
  event.preventDefault();
  const username = event.target.querySelector('#username2');
  sessionStorage.setItem("username", username.value);
  this.submit();
});

sessionStorage.clear();
// Lägg till eventlyssnare för att kunna dölja och visa sidans policy
policyLink.addEventListener("click", () => {
  policyModal.style.display = "block";
});
policyLink2.addEventListener("click", () => {
  policyModal.style.display = "block";
});
policyClose.addEventListener("click", () => {
  policyModal.style.display = "none";
});
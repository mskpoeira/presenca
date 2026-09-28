const button=document.getElementById("checkin");
const message=document.getElementById("message");
const key="presenca-checkin-2026-09-28";

if(localStorage.getItem(key)==="1"){
  button.disabled=true;
  button.textContent="Presença já confirmada neste aparelho";
  message.className="message ok";
  message.textContent="✓ Check-in já registrado neste aparelho.";
}

button.addEventListener("click",async()=>{
  button.disabled=true;
  button.textContent="Registrando…";
  message.className="message";
  message.textContent="";
  try{
    const response=await fetch("/checkin.php",{method:"POST",headers:{"Accept":"application/json"}});
    const data=await response.json();
    if(!response.ok||!data.ok)throw new Error(data.error||"Não foi possível registrar.");
    localStorage.setItem(key,"1");
    message.className="message ok";
    message.textContent="✓ Presença registrada. Obrigado!";
    button.textContent="Presença confirmada";
  }catch(error){
    button.disabled=false;
    button.textContent="Confirmar presença";
    message.className="message error";
    message.textContent=error.message||"Não foi possível registrar. Tente novamente.";
  }
});

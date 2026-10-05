const form=document.getElementById("checkin-form");
const button=document.getElementById("checkin");
const message=document.getElementById("message");
const telefone=document.getElementById("telefone");

telefone.addEventListener("input",()=>{
  const digits=telefone.value.replace(/\D/g,"").slice(0,11);
  if(digits.length<=2) telefone.value=digits;
  else if(digits.length<=6) telefone.value=`(${digits.slice(0,2)}) ${digits.slice(2)}`;
  else if(digits.length<=10) telefone.value=`(${digits.slice(0,2)}) ${digits.slice(2,6)}-${digits.slice(6)}`;
  else telefone.value=`(${digits.slice(0,2)}) ${digits.slice(2,7)}-${digits.slice(7)}`;
});

function getLocation(){
  return new Promise(resolve=>{
    if(!("geolocation" in navigator)) return resolve(null);
    navigator.geolocation.getCurrentPosition(
      position=>resolve({
        latitude:position.coords.latitude,
        longitude:position.coords.longitude,
        accuracy:Math.round(position.coords.accuracy)
      }),
      ()=>resolve(null),
      {enableHighAccuracy:false,timeout:4500,maximumAge:300000}
    );
  });
}

form.addEventListener("submit",async(event)=>{
  event.preventDefault();
  if(!form.reportValidity()) return;

  button.disabled=true;
  button.textContent="Registrando…";
  message.className="message";
  message.textContent="Validando check-in…";

  const location=await getLocation();
  const payload={
    nome:document.getElementById("nome").value.trim(),
    telefone:telefone.value.trim(),
    email:document.getElementById("email").value.trim(),
    bairro:document.getElementById("bairro").value.trim(),
    ...(location||{})
  };

  try{
    const response=await fetch("/checkin.php",{
      method:"POST",
      headers:{"Accept":"application/json","Content-Type":"application/json"},
      body:JSON.stringify(payload)
    });
    const data=await response.json();
    if(!response.ok||!data.ok) throw new Error(data.error||"Não foi possível registrar.");

    form.reset();
    message.className="message ok";
    message.textContent="✓ Presença registrada com sucesso.";
    button.textContent="Registrar outra presença";
  }catch(error){
    message.className="message error";
    message.textContent=error.message||"Não foi possível registrar. Tente novamente.";
    button.textContent="Registrar presença";
  }finally{
    button.disabled=false;
  }
});
